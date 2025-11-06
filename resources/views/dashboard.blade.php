@php
    use App\Http\Middleware\EnsureWhenRunningInDevelopmentOnly;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <title>{{ config('app.name', 'Laravel') }}</title>
        <script src="{{ asset('js/jquery-3.7.1.min.js') }}"></script>
    </head>
    <style>
        .selected-action-link {
            font-weight: bold;
        }
        .stat-value {
            text-align: right;
        }
        .ready-nation {
            color: green;
        }
    </style>
    {!! $static_js_services->renderAsTag() !!}
    {!! $static_js_territories_base_info->renderAsTag() !!}
    {!! $static_js_territories_turn_info->renderAsTag() !!}
    @if(EnsureWhenRunningInDevelopmentOnly::isRunningInDevelopmentEnvironment())
    {!! $static_js_dev_services->renderAsTag() !!}
    <script>
        let devServices = new DevServices(@json(url("")), @json(csrf_token()));
    </script>
    @endif
    <script>
        let services = new NovusOrdoServices(@json(url("")), @json(csrf_token()));

        var currentMapMode;
        const MapMode = {
            Default: 0,
            QueryTerritory: 0,
            DeployDivisions: 1,
            SelectDestinationTerritory: 2,
        };

        let victoryRanking = @json($victory_ranking);
        let budgetItems = mapExportedObject(@json($budget_items));
        let territoriesById = mergeMappedObjects(
            mapExportedArray(allTerritoriesBaseInfo, t => t.territory_id),
            mapExportedArray(allTerritoriesTurnInfo, t => t.territory_id),
            {
                turn_stats: (t, t2) => t.stats = [...t.stats, ...t2.turn_stats],
            }
        );
        let nationsById = mapExportedArray(@json($nations), n => n.nation_id);
        let allBattleLogs = @json($battle_logs);
        var deploymentsById = mapExportedArray(@json($deployments), d => d.deployment_id);
        var divisionsById = mapExportedArray(@json($divisions), d => d.division_id);
        var budget = @json($budget);

        let ownNation = @json($own_nation);
        var readyStatus = @json($ready_status);
        var forcingNextTurn = false;
        var refreshReadyStatusInterval = null;
        let runningInDevelopment = @json(EnsureWhenRunningInDevelopmentOnly::isRunningInDevelopmentEnvironment());
        let readyForNextTurnButtonEnabled = @json(config('novusordo.show_ready_for_next_turn_button'));
        var mapDisplay;
        var selectedTerritory = null;

        var selectedMainTab = null;
        const MainTabs = {
            Nation: 'Nation',
            BattleLogs: 'Battle logs',
            Deployments: 'Deployments',
        };
        
        var selectedDetailsTab = null;
        const DetailsTabs = {
            Info: 'info',
            Owner: 'owner',
            Divisions: 'divisions',
            BattleLogs: 'battle-logs',
            Deployments: 'deployments',
        };
        
        var pendingDeployments = [];

        function defaultMapLayer(ctx, md) {
            territoriesById.values().filter(t => t.owner_nation_id != null && t.owner_nation_id != ownNation.nation_id).forEach(t => md.fillTerritory(t, "red"));
            territoriesById.values().filter(t => t.owner_nation_id == ownNation.nation_id).forEach(t => md.fillTerritory(t, "blue"));
        }

        function mapExportedArray(exportedArray, keyFactory) {
            let map = new Map();

            exportedArray.forEach(value => map.set(keyFactory(value), value));

            return map;
        }
        
        function mapExportedObject(exportedObject, keyFactory) {
            let map = new Map();

            Object.entries(exportedObject).forEach(([key, value]) => {
                map.set(key, value);
            });

            return map;
        }

        function mergeObjects(o1, o2, customMappings = {}) {
            Object.keys(o2).forEach(attr => {
                if (customMappings[attr] !== undefined) {
                    customMappings[attr](o1, o2);
                }
                else {
                    o1[attr] = o2[attr];
                }
            });

            return o1;
        }

        function mergeMappedObjects(map1, map2, customMappings = {}) {
            map2.keys().forEach(k => {
                if (!map1.has(k)) {
                    // Fail silenty.
                    return;
                }

                let o1 = map1.get(k);
                let o2 = map2.get(k);

                mergeObjects(o1, o2, customMappings)
            });

            return map1;
        }

        function selectTerritory(tid) {
            let territory = territoriesById.get(tid);
            selectedTerritory = territory;
            updateTerritoryInfo();
            updateOwnerPane();
            updateDivisionsPane();
            updateTerritoryDeployments();
            updateBattleLogsPane(allBattleLogs.filter(b => b.territory_id == selectedTerritory.territory_id), $('#battle-logs-details'));
            selectDetailsPane(selectedDetailsTab ? selectedDetailsTab : 'info');
        }

        function selectMainTab(tab) {
            selectedMainTab = selectedMainTab == tab ? null : tab;
            updateMainTabs();

            stopDeploying();

            $("#details").hide();

            let mainDisplays = $("#main > div");
            mainDisplays.hide();

            switch (selectedMainTab) {
                case 'Nation':
                    $("#nation-display").show();
                    break;
                case 'BattleLogs':
                    $("#battle-logs-display").show();
                    break;
                case 'Deployments':
                    $("#deployments-display").show();
                    startDeploying();
                    break;
                case null:
                    if (selectedTerritory) {
                        $("#details").show();
                    }
                    break;
                default:
                    throw new Error("Unreacheable.");
            }
        }

        function updateMainTabs() {
            $("#main-tabs").html(Object.keys(MainTabs).map(tab => renderActionLink(MainTabs[tab], `selectMainTab('${tab}')`, tab == selectedMainTab)).join(" "));
        }

        function formatValue(value, unit) {
            switch (unit) {
                case StatUnit.Percent:
                    return `${(value * 100).toFixed(0)}%`;
                case StatUnit.Km2:
                    return `${Intl.NumberFormat().format(value)} km<sup>2</sup>`;
                default:
                    throw new Error("Unreacheable.");
            }
        }

        function renderDemography(stats) {
            return '<div><h2>Demographics</h2><table>'
                + stats.map(stat => `<tr><td>${stat.title}</td><td class="stat-value">${formatValue(stat.value, stat.unit)}</td></tr>`).join("")
                + '</table></div>';
        }

        function updateTerritoryInfo() {
            $("#territory-info").html(
                `<p><h1>${selectedTerritory.name}</h1><br>`
                + (selectedTerritory.terrain_type == TerrainType.Water
                    ? "Sea"
                    : (selectedTerritory.has_sea_access ? "Coastal" : "No sea access")
                )
                + (selectedTerritory.connected_territory_ids.length > 0 ? '. Land access to ' + selectedTerritory.connected_territory_ids.map(ctid => territoriesById.get(ctid)).map(t => renderActionLink(t.name, `selectTerritory(${t.territory_id})`)).join(', ') : '')
                + '</p>'
                + (selectedTerritory.owner_nation_id != null ? `<p>Owned by ${nationsById.get(selectedTerritory.owner_nation_id).usual_name}</p>` : '')
            );

            $("#info-details").html(renderDemography(selectedTerritory.stats));
        }

        function updateOwnerPane() {
            if (selectedTerritory.owner_nation_id !== undefined) {
                var nation = nationsById.get(selectedTerritory.owner_nation_id);
                var html = "";
                html += `<h1><b>${nation.usual_name}</b></h1>`;
                html += renderDemography(nation.stats);
                $("#owner-details").html(html);
            }
            else {
                $("#owner-details").html("<h1><b>Neutral territory</b></h1>");
            }
        }

        function updateNationPane(nation, component) {
            if (nation !== undefined) {
                component.html(`<h1><b>${nation.usual_name}</b></h1>`);
            }
            else {
                throw new Error("Unreacheable.");
            }
        }

        function updateBudgetPane() {
            $('#budget-details').html(
                '<table>'
                + budgetItems.keys().toArray().map(key => {
                    let item = budgetItems.get(key);
                    return item.type == "Asset"
                        ? `<tr><td>${item.description}</td><td><i>${budget[key]}</i></td></tr>`
                        : `<tr><td>${item.description}</td><td><i style="color: crimson">${budget[key]}</i></td></tr>`;
                }).join("")
                + '</table>'
            );
        }

        function updateVictoryPane() {
            $('#victory-details').html(
                '<table>'
                + victoryRanking.map((rankInfo, i) => `<tr><td>${i + 1}</td><td>${nationsById.get(rankInfo.nationId).usual_name}</td><td>${(rankInfo.progress * 100).toFixed(2)}% (owns ${rankInfo.numberOfTerritories} of ${rankInfo.numberOfTerritoriesRequired} required territories)</td></tr>`).join("")
                + '</table>'
            );
        }

        function updateBattleLogsPane(battleLogs, component) {
            if (battleLogs.length == 0) {
                component.html("<p>We didn't participate in any battle this turn.</p>");
            }
            else {
                component.html(
                    battleLogs.map(battleLog => {
                        let destinationTerritory = territoriesById.get(battleLog.territory_id);
                        var summary;
                        if (ownNation.nation_id == battleLog.attacker_nation_id) {
                            if (ownNation.nation_id == battleLog.winner_nation_id) {
                                summary = `<span style="color: green">We conquered ${destinationTerritory.name}!</span>`;
                            }
                            else {
                                summary = `<span style="color: red">The attack on ${destinationTerritory.name} was repelled!</span>`;
                            }
                        }
                        else {
                            if (ownNation.nation_id == battleLog.winner_nation_id) {
                                summary = `<span style="color: orange">We repelled an attack from ${nationsById.get(battleLog.attacker_nation_id).usual_name} on ${destinationTerritory.name}!</span>`;
                            }
                            else {
                                summary = `<span style="color: red">We were defeated on ${destinationTerritory.name} and ${nationsById.get(battleLog.attacker_nation_id).usual_name} annexed the territory!</span>`;
                            }
                        }

                        return `<p><b>${summary}</b></p>`
                            + `<pre>${battleLog.text}</pre>`;
                    }).join("")
                );
            }
        }

        function updateTerritoryDeployments() {
            if (!selectedTerritory) {
                return;
            }
            
            deploymentsInTerritory = deploymentsById.values().filter(d => d.territory_id == selectedTerritory.territory_id).toArray();

            $("#deployments-details").html(renderDeploymentList(deploymentsInTerritory));
        }

        function renderDeploymentList(deployments) {
            var html = "";

            if (deployments.length > 0) {
                html += '<p>Will be deployed next turn:</p>'
                    + '<p>'
                    + `<ul>${deployments.map(d => `<li><a href="javascript:void(0)" onclick="selectTerritory(${d.territory_id})">${territoriesById.get(d.territory_id).name}</a> - <a href="javascript:void(0)" onclick="cancelDeployment(${d.deployment_id})">cancel</a></li>`).join("")}</ul>`
                    + '</p>';
            }
            else {
                html += "<p>No confirmed deployments for now.</p>"
            }

            return html;
        }

        function updateDeploymentsPane() {
            var html = "";
            let remainingDeployments = budget.max_remaining_deployments - pendingDeployments.length;

            html += `<p>You can still deploy</a> ${remainingDeployments} divisions this turn.`;

            if (pendingDeployments.length > 0) {
                html += '<p>Pending deployments (<a href="javascript:void(0)" onclick="confirmAllPendingDeployment()">confirm all</a> or <a href="javascript:void(0)" onclick="cancelAllPendingDeployment()">cancel all</a>): '
                    + '<p>'
                    + `<ul>${pendingDeployments.map(tid => `<li><a href="javascript:void(0)" onclick="selectTerritory(${tid})">${territoriesById.get(tid).name}</a> - <a href="javascript:void(0)" onclick="removeDeployment(${tid})">cancel</a></li>`).join("")}</ul>`
                    + '</p>';
            }

            html += renderDeploymentList(deploymentsById.values().toArray());

            $('#deployments-display').html(html);
        }

        function updateDivisionsPane() {
            var html = "";

            divisionsInTerritory = divisionsById.values().filter(d => d.territory_id == selectedTerritory.territory_id).toArray();
            
            if (divisionsInTerritory.length < 1) {
                html += "<p>There is no divisions in this territory.</p>";
            }
            else {
                html += `<p>Divisions in territory<span id="select-divisions-links"></span>:</p>`;
                html += `<span id="send-order-link">&nbsp;</span>`
                html += '<div id="territory-division-list"><ul>'
                    + divisionsInTerritory.map(d => `<li><input type="checkbox" onchange="onDivisionSelectionChange()" value=${d.division_id}>${d.division_type} division #${d.division_id}`
                    + (d.order ? ` <i> ${describeOrder(d.order)}</i>` : "")
                    + ` ${d.order ? `<a href="javascript:void(0)" onclick="cancelOrder(${d.division_id})">cancel order</a></li>`: ""}`).join("")
                    + '</ul></div>';
            }
            
            $("#divisions-details").html(html);
            onDivisionSelectionChange();
        }

        function onDivisionSelectionChange() {
            let numberOfSelectedDivisions = getAllSelectedDivisionsInTerritory().length;
            if (numberOfSelectedDivisions > 0) {
                $("#send-order-link").html(currentMapMode == MapMode.SelectDestinationTerritory
                    ? `Select the destination territory to move to attack with the ${numberOfSelectedDivisions} selected divisions or <a href="javascript:void(0)" onclick="cancelSelectDestinationForDivisions()">cancel</a>`
                    : `With ${numberOfSelectedDivisions} selected divisions: <a href="javascript:void(0)" onclick="selectDestinationForDivisions()">move / attack</a> - <a href="javascript:void(0)" onclick="sendDisbandOrdersToSelectedDivisions()">disband</a>`
                );
            }
            else {
                $("#send-order-link").html("&nbsp;");
            }

            if (currentMapMode == MapMode.SelectDestinationTerritory) {
                $("#select-divisions-links").html("");
            }
            else {
                $("#select-divisions-links").html(` (<a href="javascript:void(0)" onclick="selectAllDivisionsInTerritory(true)">select</a> / <a href="javascript:void(0)" onclick="selectAllDivisionsInTerritory(false)">deselect</a> all)`);
            }
        }

        function describeOrder(order) {
            if (order.order_type == OrderType.Move) {
                let destination = territoriesById.get(order.destination_territory_id);
                let destinationLink = renderActionLink(`${destination.name} (${destination.owner_nation_id ? nationsById.get(destination.owner_nation_id).usual_name : "neutral"})`, `selectTerritory(${destination.territory_id})`);

                if (destination.owner_nation_id == ownNation.nation_id) {
                    return `moving to ${destinationLink}`;
                }
                else {
                    return `attacking ${destinationLink}`;
                }
            }
            else if (order.order_type == OrderType.Disband) {
                return "disbanding";
            }
            else {
                return "#UNKNOWN ORDER#";
            }
        }

        function selectDestinationForDivisions() {
            [...document.getElementById('territory-division-list').getElementsByTagName("input")].forEach(cb => cb.disabled = true);
            setMapMode(MapMode.SelectDestinationTerritory);
            onDivisionSelectionChange();
        }

        function cancelSelectDestinationForDivisions() {
            setMapMode(MapMode.Default);
            [...document.getElementById('territory-division-list').getElementsByTagName("input")].forEach(cb => cb.disabled = false);
            onDivisionSelectionChange();
        }

        function sendDisbandOrdersToSelectedDivisions() {
            let selectedDivisions = getAllSelectedDivisionsInTerritory();
            services.sendDisbandOrders({orders: selectedDivisions.map(d => ({ division_id: d.division_id }))})
                .then(data => patchOrders(data))
                .catch(error => {
                    $("#error_messages").html(`<li style="color: crimson">${JSON.stringify(error.responseJSON)}}</li>`);
                });
        }

        function sendMoveOrderToSelectedDivisions(tid) {
            setMapMode(MapMode.Default);
            let selectedDivisions = getAllSelectedDivisionsInTerritory();
            services.sendMoveOrders({orders: selectedDivisions.map(d => ({ division_id: d.division_id, destination_territory_id: tid }))})
                .then(data => patchOrders(data))
                .catch(error => {
                    $("#error_messages").html(`<li style="color: crimson">${JSON.stringify(error.responseJSON)}}</li>`);
                });
        }

        function cancelOrder(did) {
            if (currentMapMode == MapMode.SelectDestinationTerritory) {
                return;
            }
            services.cancelOrders({
                division_ids: [did]
            })
            .then(() => {
                divisionsById.get(did).order = null;
                updateDivisionsPane();
            })
            .catch(error => {
                $("#error_messages").html(`<li style="color: crimson">${JSON.stringify(error.responseJSON)}}</li>`);
            });
        }

        function getAllSelectedDivisionsInTerritory() {
            let thereAreDivisionsInTerritory = document.getElementById('territory-division-list');
            if (!thereAreDivisionsInTerritory) {
                return [];
            }
            return [...document.getElementById('territory-division-list').getElementsByTagName("input")]
                .filter(cb => cb.checked)
                .map(cb => divisionsById.get(parseInt(cb.value)));
        }

        function selectAllDivisionsInTerritory(checked) {
            [...document.getElementById('territory-division-list').getElementsByTagName("input")].forEach(cb => cb.checked = checked);
            onDivisionSelectionChange();
        }

        function selectDetailsPane(pane) {
            selectedDetailsTab = pane;
            updateDetailsTabs();
            selectMainTab(null);
            let detailsPanes = $("#details-panes > div");
            detailsPanes.hide();
            $(`#${pane}-details`).show();
        }

        function updateDetailsTabs() {
            $("#details-tabs").html(Object.keys(DetailsTabs).map(pane => renderActionLink(pane, `selectDetailsPane('${DetailsTabs[pane]}')`, DetailsTabs[pane] == selectedDetailsTab)).join(" "));
        }

        function renderActionLink(title, onclick, selected = false) {
            return `<a class="${selected ? "selected-action-link" : "action-link"}" href="javascript:void(0)" onclick="${onclick}">${title}</a>`;
        }

        function stopDeploying() {
            setMapMode(MapMode.Default);
        }

        function startDeploying() {
            setMapMode(MapMode.DeployDivisions);
        }

        function addDeployment(tid) {
            if (pendingDeployments.length >= budget.max_remaining_deployments) {
                return;
            }

            pendingDeployments.push(tid);
            updateDeploymentsPane();
            mapDisplay.refresh();
        }

        function removeDeployment(tid) {
            if (pendingDeployments.length < 1) {
                return;
            }

            const index = pendingDeployments.indexOf(tid);
            if (index !== -1) {
                pendingDeployments.splice(index, 1); // Removes 1 element starting from the found index
            }
            updateDeploymentsPane();
            mapDisplay.refresh();
        }

        function confirmAllPendingDeployment() {
            $("#deployments-detail").html("<p>Waiting for the server to respond...</p>");
            let pendingDeploymentsByTerritoryId = Map.groupBy(pendingDeployments, tid => tid);
            pendingDeployments.length = 0;
            
            let callChain = Promise.resolve();

            pendingDeploymentsByTerritoryId.forEach((group, tid) => {
                callChain = callChain
                    .then(() => {
                        return services.deployInTerritory(tid, { division_type: DivisionType.Infantry, number_of_divisions: group.length })
                            .then(deployments => deployments.forEach(d => deploymentsById.set(d.deployment_id, d)))
                            .then(updateTerritoryDeployments);
                    })
            });

            callChain
                .then(refreshBudget)
                .then(updateDeploymentsPane)
                .then(() => mapDisplay.refresh())
                .catch(error => {
                    $("#error_messages").html(`<li style="color: crimson">${JSON.stringify(error.responseJSON)}}</li>`);
                });
        }

        function cancelDeployment(deploymentId) {
            services.cancelDeployments({deployment_ids: [deploymentId]})
                .then(() => {
                    deploymentsById.delete(deploymentId);
                    updateTerritoryDeployments();
                })
                .then(refreshBudget)
                .then(updateDeploymentsPane)
                .then(() => mapDisplay.refresh())
                .catch(error => {
                    $("#error_messages").html(`<li style="color: crimson">${JSON.stringify(error.responseJSON)}}</li>`);
                });
        }
        function refreshBudget() {
            return services.getNationBudget()
                .then((data) => {
                    budget = data;
                })
                .then(() => {
                    updateBudgetPane();
                });
        }

        function patchOrders(newOrders) {
            newOrders.forEach(order => divisionsById.get(order.division_id).order = order);
            updateDivisionsPane();
        }

        function cancelAllPendingDeployment() {
            pendingDeployments.length = 0;
            updateDeploymentsPane();
            mapDisplay.refresh();
        }
        
        function setMapMode(mode) {
            currentMapMode = mode;
            switch(mode) {
                case MapMode.QueryTerritory:
                    mapDisplay.setAllClickable(true);
                    mapDisplay.onClick = selectTerritory;
                    mapDisplay.onContextMenu = undefined;
                    mapDisplay.setLayers([defaultMapLayer]);
                    mapDisplay.setTopLayers([(ctx, md) => {
                        if (selectedTerritory) {
                            md.fillTerritory(selectedTerritory, "black");
                            md.labelTerritory(selectedTerritory, "?", "white");
                        }
                    }]);
                    break;
                case MapMode.DeployDivisions:
                    let deployableTerritoryIds = [];
                    territoriesById.values().forEach(t => {
                        if (t.owner_nation_id == ownNation.nation_id) {
                            deployableTerritoryIds.push(t.territory_id);
                        }
                    })
                    territoriesById.values().forEach(t => mapDisplay.setClickable(t.territory_id, deployableTerritoryIds.includes(t.territory_id)));
                    mapDisplay.onClick = addDeployment;
                    mapDisplay.onContextMenu = (tid) => {
                        removeDeployment(tid);
                    }
                    mapDisplay.setLayers([defaultMapLayer]);
                    mapDisplay.setTopLayers([(ctx, md) => {
                        currentDeploymentsByTerritoryId = Map.groupBy(deploymentsById.values(), d => d.territory_id);
                        pendingDeploymentsByTerritoryId = Map.groupBy(pendingDeployments, tid => tid);
                        deployableTerritoryIds.forEach(tid => {
                            let numberOfCurrent = currentDeploymentsByTerritoryId.has(tid) ? currentDeploymentsByTerritoryId.get(tid).length : 0;
                            let numberOfPending = pendingDeploymentsByTerritoryId.has(tid) ? pendingDeploymentsByTerritoryId.get(tid).length : 0;
                            if (numberOfCurrent + numberOfPending > 0) {
                                var label = "";
                                if (numberOfPending > 0) {
                                    label += `+${Math.min(99, numberOfPending)}`;
                                }
                                if (numberOfCurrent > 0) {
                                    label =  Math.min(99, numberOfCurrent).toString() + label;
                                }
                                md.labelTerritory(territoriesById.get(tid), label, "white");
                            }
                        });
                    }]);
                    break;
                case MapMode.SelectDestinationTerritory:
                    let origin = selectedTerritory;
                    let legalDestinations = territoriesById.values()
                        .filter(t => t.terrain_type != TerrainType.Water && t.territory_id != origin.territory_id)
                        .filter(t =>
                            t.connected_territory_ids.includes(origin.territory_id)
                            || t.has_sea_access && origin.has_sea_access
                        ).toArray();
                    territoriesById.values().forEach(t => mapDisplay.setClickable(t.territory_id, legalDestinations.some(dest => dest.territory_id == t.territory_id)));
                    mapDisplay.onClick = sendMoveOrderToSelectedDivisions;
                    mapDisplay.onContextMenu = undefined;
                    mapDisplay.setLayers([defaultMapLayer, (ctx, md) => {
                        legalDestinations
                            .forEach(t => t.owner_nation_id == ownNation.nation_id ? md.fillTerritory(t, "blue") : md.fillTerritory(t, "red"));
                    }]);
                    mapDisplay.setTopLayers([]);
                    break;
                default:
                    throw new Error("Unreacheable.");
            }
            mapDisplay.refresh();
        }

        function forceNextTurn() {
            forcingNextTurn = true;
            document.getElementById("force-next-turn-button").disabled = true;
            stopCheckingForNextTurn();

            devServices.forceNextTurn({ turn_number: ownNation.turn_number })
                .then((data) => {
                    if (ownNation.turn_number == data.turn_number) {
                        throw new Error(`Force next turn failed: turn number is still ${ownNation.turn_number}`);
                        return;
                    }

                    window.location.reload();
                });
        }

        function readyForNextTurn() {
            ownNation.is_ready_for_next_turn = true;
            readyStatus.ready_for_next_turn_nation_ids.push(ownNation.nation_id);
            updateReadyStatus();

            services.readyForNextTurn({ turn_number: ownNation.turn_number })
                .then(data => {
                    readyStatus = data;
                    if (readyStatus.turn_number != ownNation.turn_number) {
                        window.location.reload();
                    }
                    else {
                        updateReadyStatus();
                        startCheckingForNextTurn();
                    }
                });
        }

        function startCheckingForNextTurn() {
            refreshReadyStatusInterval = setInterval(() => {
                services.getGameReadyStatus()
                    .then(data => readyStatus = data)
                    .then(updateReadyStatus);
            }, 1000);
        }

        function stopCheckingForNextTurn() {
            if (refreshReadyStatusInterval) {
                clearInterval(refreshReadyStatusInterval);
            }
        }

        function updateReadyStatus() {
            $("#force-next-turn-section").hide();
            $("#ready-for-next-turn-section").hide();
            $("#ready-for-next-turn-status").hide();

            if (!readyForNextTurnButtonEnabled) {
                $("#force-next-turn-section").show();
            }
            else if (ownNation.is_ready_for_next_turn) {
                $("#force-next-turn-section").show();
                $("#ready-for-next-turn-status").html(`${readyStatus.ready_for_next_turn_nation_ids.length} out of ${readyStatus.nation_count} are ready for next turn: ${nationsById.values().map(n => `<span class="${readyStatus.ready_for_next_turn_nation_ids.includes(n.nation_id) ? "ready-nation" : "not-ready-nation"}">${n.usual_name}</span>`).toArray().join(", ")}`);
                $("#ready-for-next-turn-status").show();
            }
            else {
                $("#ready-for-next-turn-section").show();
            }
        }

        window.addEventListener("load", function() {
            mergeObjects(ownNation, nationsById.get(ownNation.nation_id), { stats: (o1, o2) => o1.stats = [...o1.stats, ...o2.stats] });
            mapDisplay = new MapDisplay("map-display", territoriesById, md => {
                md.territoryLabeler = t => `${t.name} (${nationsById.has(t.owner_nation_id) ? nationsById.get(t.owner_nation_id).usual_name : "neutral"})`;
                md.addInternationalBorders = true;
                md.setLayers([defaultMapLayer]);
            });
            setMapMode(MapMode.Default);
            updateMainTabs();
            updateDetailsTabs();
            updateNationPane(ownNation, $('#own-nation-details'));
            $('#own-nation-demographics').html(renderDemography(ownNation.stats));
            updateVictoryPane();
            updateBudgetPane();
            updateBattleLogsPane(allBattleLogs, $('#battle-logs-display'));
            updateDeploymentsPane();
            $("#details").hide();
            selectMainTab(null);
            updateReadyStatus();
            if (ownNation.is_ready_for_next_turn) {
                startCheckingForNextTurn();
            }
            if (runningInDevelopment && readyForNextTurnButtonEnabled) {
                document.getElementById("force-next-turn-button").disabled = true;
                document.addEventListener('keydown', function(event) {
                    document.getElementById("force-next-turn-button").disabled = !(!forcingNextTurn && event.shiftKey);
                });
                document.addEventListener('keyup', function(event) {
                    document.getElementById("force-next-turn-button").disabled = !(!forcingNextTurn && event.shiftKey);
                });
            }
        });
    </script>
    <body>
        <div>
        <b>{{ $context->getNation()->getUsualName() }}</b>, turn #{{ $context->getCurrentTurn()->getNumber() }}
            <a href="{{route('logout')}}">logout</a>
            @if(EnsureWhenRunningInDevelopmentOnly::isRunningInDevelopmentEnvironment())
                <div id="force-next-turn-section">
                    <button id="force-next-turn-button" class="btn btn-primary" title="CTRL-click to force the end of the turn." onclick="forceNextTurn()">Force next turn</button>
                </div>
            @endif
            @if(config('novusordo.show_ready_for_next_turn_button'))
                <div id="ready-for-next-turn-section">
                    <button class="btn btn-primary" onclick="readyForNextTurn()">Ready for next turn</button>
                </div>
                <div id="ready-for-next-turn-status">
                </div>
            @endif
            <x-dev-mode />
        </div>
        <span id="main-tabs"></span>
        <x-map-display id="map-display" />
        <div id="main">
            <div id="nation-display">
                <div id="own-nation-details">
                    own nation
                </div>
                <div id="own-nation-demographics">
                    budget
                </div>
                <h3>Budget</h3>
                <div id="budget-details">
                    budget
                </div>
                <h3>Victory progression</h3>
                <div id="victory-details">
                victory
            </div>
            </div>
            <div id="battle-logs-display">
                battle logs
            </div>
            <div id="deployments-display">
                deployments
            </div>
        </div>
        <div id="details">
            <div id="details-header">
                <span id="details-tabs"></span>
                <div id="territory-info">
                    infos
                </div>
            </div>
            <div id="details-panes">
                <div id="info-details">
                    info
                </div>
                <div id="owner-details">
                    owner
                </div>
                <div id="battle-logs-details">
                    battle logs
                </div>
                <div id="deployments-details">
                    deployments
                </div>
                <div id="divisions-details">
                    divisions
                </div>
            </div>
        </div>
    </body>
</html>
