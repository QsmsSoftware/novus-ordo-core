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
        .full-sized-flag {
            outline: 1px solid black;
            width: 300px;
            height: 200px;
        }
        .resource-bar {
            display: flex;
            width: 900px;
        }
        .resource-box {
            flex: 1; /* Each column takes up an equal share of space */
        }
        .surplus-balance {
            color: green;
        }
        .deficit-balance {
            color: crimson;
        }
        .resource-icon {
            width: 32px;
            height: 32px;
        }
        .victor-icon {
            width: 16px;
            height: 16px;
        }
        .division-resource-icon {
            width: 16px;
            height: 16px;
        }
    </style>
    {!! $static_js_services->renderAsTag() !!}
    {!! $static_js_territories_base_info->renderAsTag() !!}
    {!! $static_js_territories_turn_info->renderAsTag() !!}
    {!! $static_js_rankings->renderAsTag() !!}
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
            SelectMoveDestinationTerritory: 2,
            SelectAttackTargetTerritory: 3,
        };
        const MapView = {
            Political: 0,
            OwnNation: 1,
            Battles: 2,
            Armies: 3,
            Deployments: 4,
        };

        let victoryStatus = @json($victory_status);
        let budgetItems = mapExportedObject(@json($budget_items));
        let ownTerritoriesTurnInfo = @json($own_territories_turn_info);
        let territoriesById = mergeMappedObjects(
            mergeMappedObjects(
                mapExportedArray(allTerritoriesBaseInfo, t => t.territory_id),
                mapExportedArray(allTerritoriesTurnInfo, t => t.territory_id)
            ),
            mapExportedArray(ownTerritoriesTurnInfo, t => t.territory_id)
        );
        let nationsById = mapExportedArray(@json($nations), n => n.nation_id);
        let allBattleLogs = @json($battle_logs);
        var deploymentsById = mapExportedArray(@json($deployments), d => d.deployment_id);
        var divisionsById = mapExportedArray(@json($divisions), d => d.division_id);
        let resourceTypeInfoByType = mapExportedArray(allResourceTypes, rt => rt.resource_type);
        let divisionTypeInfoByType = mapExportedArray(allDivisionTypes, dt => dt.division_type);
        let terrainTypeInfoByType = mapExportedArray(allTerrainTypes, tt => tt.terrain_type);
        var budget = @json($budget);

        let ownNation = @json($own_nation);
        var readyStatus = @json($ready_status);
        var forcingNextTurn = false;
        var refreshReadyStatusInterval = null;
        var updatingTimeRemainingInterval = null;
        let runningInDevelopment = @json(EnsureWhenRunningInDevelopmentOnly::isRunningInDevelopmentEnvironment());
        let readyForNextTurnButtonEnabled = @json(config('novusordo.show_ready_for_next_turn_button'));
        var mapDisplay;
        var selectedTerritory = null;

        var selectedMainTab = null;
        const MainTabs = {
            Nation: 'Nation',
            BattleLogs: 'Battle logs',
            Deployments: 'Deployments',
            Rankings: 'Rankings',
            Goals: 'Goals',
        };
        
        var selectedDetailsTab = null;
        const DetailsTabs = {
            Info: {
                id: 'info',
                mapView: MapView.Political,
            },
            Owner: {
                id: 'owner',
                mapView: MapView.Political,
            },
            Divisions: {
                id: 'divisions',
                mapView: MapView.Armies,
            },
            BattleLogs: {
                id: 'battle-logs',
                mapView: MapView.Battles,
            },
            Deployments: {
                id: 'deployments',
                mapView: MapView.Deployments,
            },
        };
        
        var selectedDivisionType;
        var pendingDeployments = [];

        const StorageKey = {
            SelectedTerritoryId: 'selectedTerritoryId',
            SelectedMainTab: 'selectedMainTab',
            SelectedDetailsTab: 'selectedDetailsTab',
        };

        let siteBaseUri = document.baseURI.substring(0, document.baseURI.indexOf('/dashboard'));

        function getRelativeUri(uri) {
            if (uri.startsWith(siteBaseUri)) {
                return uri.substring(siteBaseUri.length + 1);
            }

            return uri;
        }

        function ownNationHighlightMapLayer(ctx, md) {
            territoriesById.values().filter(t => t.owner_nation_id == ownNation.nation_id).forEach(t => {
                md.fillTerritory(t, "black");
            });
        }

        function nationHighlightMapLayer(ctx, md) {
            territoriesById.values().filter(t => t.owner_nation_id != null).forEach(t => {
                let nationOrNull = getSelectedNationOrNull();
                if (!nationOrNull) {
                    return;
                }
                if (nationOrNull.nation_id == t.owner_nation_id) {
                    md.fillTerritory(t, "black");
                }
            });
        }

        function getTerritoryIdsWithBattles() {
            return [...new Set(allBattleLogs.map(l => l.territory_id))];
        }

        function battlesHighlightMapLayer(ctx, md) {
            getTerritoryIdsWithBattles().forEach(tid => {
                md.fillTerritory(territoriesById.get(tid), "black");
            });
        }

        function armiesHighlightMapLayer(ctx, md) {
            [...new Set(divisionsById.values().map(d => d.territory_id))].forEach(tid => {
                md.fillTerritory(territoriesById.get(tid), "black");
            });
        }

        function deploymentsHighlightMapLayer(ctx, md) {
            [...new Set(deploymentsById.values().map(d => d.territory_id))].forEach(tid => {
                md.fillTerritory(territoriesById.get(tid), "black");
            });
        }

        function relationsMapLayer(ctx, md) {
            territoriesById.values().filter(t => t.owner_nation_id != null && t.owner_nation_id != ownNation.nation_id).forEach(t => {
                md.fillTerritory(t, "red");
            });
            territoriesById.values().filter(t => t.owner_nation_id == ownNation.nation_id).forEach(t => {
                md.fillTerritory(t, "blue");
            });
        }

        function getNationFlagImgOrNull(nid) {
            return document.getElementById(`img_flag_${nid}`);
        }

        function politicalMapLayer(ctx, md) {
            territoriesById.values().filter(t => t.owner_nation_id != null).forEach(t => {
                flagOrNull = getNationFlagImgOrNull(t.owner_nation_id);

                if (flagOrNull) {
                    md.fillTerritoryWithImage(t, flagOrNull);
                }
            });
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
                else if (Array.isArray(o1[attr]) && Array.isArray(o2[attr])) {
                    o1[attr] = [...o1[attr], ...o2[attr]];
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

        function getSelectedNationOrNull() {
            if (!selectedTerritory) {
                return null;
            }

            if (selectedTerritory.owner_nation_id && nationsById.has(selectedTerritory.owner_nation_id)) {
                return nationsById.get(selectedTerritory.owner_nation_id);
            }

            return null;
        }

        function parseIntOrNull(strValue) {
            let parsedValue = parseInt(strValue);
            return !isNaN(parsedValue) && String(parsedValue) === String(strValue).trim() ? parsedValue : null;
        }

        function notEmptyStringOrNull(value) {
            if (typeof value !== 'string') {
                return null;
            }

            return value == "" ? null : value;
        }

        function storeSelectedDetailsTabToStorage(detailsTab) {
            sessionStorage.setItem(StorageKey.SelectedDetailsTab, detailsTab);
        }

        function getSelectedDetailsTabFromStorage() {
            return notEmptyStringOrNull(sessionStorage.getItem(StorageKey.SelectedDetailsTab));
        }

        function storeSelectedTerritoryIdToStorage(tid) {
            sessionStorage.setItem(StorageKey.SelectedTerritoryId, tid);
        }

        function getSelectedTerritoryIdFromStorage() {
            return parseIntOrNull(sessionStorage.getItem(StorageKey.SelectedTerritoryId));
        }

        function selectTerritory(tid) {
            let territory = territoriesById.get(tid);
            selectedTerritory = territory;
            storeSelectedTerritoryIdToStorage(tid);
            updateTerritoryInfo();
            updateOwnerPane();
            updateDivisionsPane();
            updateTerritoryDeployments();
            updateBattleLogsPane(allBattleLogs.filter(b => b.territory_id == selectedTerritory.territory_id), $('#battle-logs-details'));
            selectDetailsPane(selectedDetailsTab ? selectedDetailsTab : 'info');
        }

        function storeSelectedMainTabToStorage(tab) {
            sessionStorage.setItem(StorageKey.SelectedMainTab, tab ? tab : "");
        }

        function getSelectedMainTabFromStorage() {
            return notEmptyStringOrNull(sessionStorage.getItem(StorageKey.SelectedMainTab));
        }

        function selectMainTab(tab) {
            selectedMainTab = selectedMainTab == tab ? null : tab;
            storeSelectedMainTabToStorage(selectedMainTab);
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
                    startDeploying(DivisionType.Infantry);
                    break;
                case 'Rankings':
                    $("#rankings-display").show();
                    break;
                case 'Goals':
                    $("#goals-display").show();
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

        function renderTime(time_s) {
            const hours = Math.floor(time_s / (1000 * 60 * 60));
            const minutes = Math.floor((time_s % (1000 * 60 * 60)) / (1000 * 60));
            //const seconds = Math.floor((time_s % (1000 * 60)) / 1000);

            //return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
        }

        function formatValue(value, unit) {
            const defaultLocale = navigator.language;

            switch (unit) {
                case StatUnit.Percent:
                    return `${(value * 100).toFixed(0)}%`;
                case StatUnit.DetailedPercent:
                    return `${(value * 100).toFixed(2)}%`;
                case StatUnit.Km2:
                    return `${Intl.NumberFormat().format(value)} km<sup>2</sup>`;
                case StatUnit.WholeNumber:
                    return Intl.NumberFormat().format(value);
                case StatUnit.ApproximateNumber:
                    return '~' + Intl.NumberFormat().format(value);
                case StatUnit.DecimalNumber:
                    return Intl.NumberFormat(defaultLocale, {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    }).format(value);
                case StatUnit.Unknown:
                    return 'Unknown';
                default:
                    throw new Error("Unreacheable.");
            }
        }

        function renderDemography(stats) {
            return '<div><h2>Demographics</h2><table>'
                + stats.map(stat => `<tr><td>${stat.title}</td><td class="stat-value">${formatValue(stat.value, stat.unit)}</td></tr>`).join("")
                + '</table></div>';
        }

        function renderProduction(territory) {
            let ownerLoyalty = territory.owner_nation_id ? territory.loyalties.find(l => l.nation_id == territory.owner_nation_id) : null;
            return '<div><h2>Production</h2><table>'
                + '<tr><th>Base production</th><th>Loyal population (M)</th><th>Production</th></th>'
                + resourceTypeInfoByType.keys().map(resourceType => `<tr><td class="stat-value">${resourceTypeInfoByType.get(resourceType).base_production_by_terrain_type[territory.terrain_type].toFixed(2)}x</td><td class="stat-value">${formatValue(ownerLoyalty ? ownerLoyalty.loyalty_ratio * territory.stats.find(s => s.title == TerritoryStat.Population).value / 1_000_000 : 0, StatUnit.DecimalNumber)}</td><td class="stat-value">${territory.owner_production ? territory.owner_production[resourceType].toFixed(2) : ""}x</td><td>${renderProductionResourceIcon(resourceType)}</td></tr>`).toArray().join("")
                + '</table></div>';
        }

        function renderLoyalties(loyalties) {
            return '<div><h2>Loyalties</h2>'
                + (loyalties.length > 0
                    ? '<table>' + loyalties.map(loyalty => `<tr><td>${nationsById.get(loyalty.nation_id).usual_name}</td><td class="stat-value">${formatValue(loyalty.loyalty_ratio, StatUnit.Percent)}</td></tr>`).join("") + '</table>'
                    : '<i>The population on this territory has no loyalty toward any nation.</i>'
                )
                + '</div>';
        }

        function showAssetInfo(uri) {
            let popover = document.getElementById('asset-info');
            services.getAssetInfo(encodeURIComponent(encodeURIComponent(uri)))
                .then((assetInfo)=> {
                    popover.innerHTML =
                        '<p><b>' + (assetInfo.title ? assetInfo.title : '(Untitled)') + '</b>'
                        + (assetInfo.attribution ? `<br>Attribution: ${assetInfo.attribution}` : '')
                        + (assetInfo.license ? `<br>License: ${assetInfo.license_uri ? `<a href="${assetInfo.license_uri}" target="_blank" rel="noopener noreferrer">${assetInfo.license}</a>` : assetInfo.license}` : '')
                        + '</p>'
                        + (assetInfo.description ? `<p>Description:<br>${assetInfo.description}</p>` : '');

                    popover.togglePopover();
                })
                .catch(() => {
                    popover.innerHTML =
                        '<p><b>Content with no attribution information.</b></p>';

                    popover.togglePopover();
                });

            
        }

        function renderNationFlagFullSize(nation) {
            let flag = getNationFlagImgOrNull(nation.nation_id);

            return `<img class="full-sized-flag" src="${flag.src}" title="Flag of the ${nation.formal_name}" alt="Flag of the ${nation.formal_name}">`;
        }

        function renderNationFlagSection(nation) {
            let flag = getNationFlagImgOrNull(nation.nation_id);

            if (flag) {
                return '<div><h2>Flag</h2>'
                    + renderNationFlagFullSize(nation)
                    + ` <a href="javascript:void(0)" title="About this picture" onclick="showAssetInfo('${getRelativeUri(flag.src)}', 'popover_target_flag_${nation.nation_id}')">i</a>`
                    + '</div>';
            }
            
            return '<div>This nation has no official flag yet.<div>';
        }

        function updateTerritoryInfo() {
            $("#territory-info").html(
                `<h1>${selectedTerritory.name}</h1>`
                + (selectedTerritory.terrain_type == TerrainType.Water
                    ? "Sea"
                    : (selectedTerritory.has_sea_access ? "Coastal" : "No sea access")
                )
                + (selectedTerritory.connected_land_territory_ids.length > 0 ? '. Land access to ' + selectedTerritory.connected_land_territory_ids.map(ctid => territoriesById.get(ctid)).map(t => renderActionLink(t.name, `selectTerritory(${t.territory_id})`)).join(', ') : '')
                + `<span id="territory-info-owner">` + (selectedTerritory.owner_nation_id != null ? `<p>Owned by ${nationsById.get(selectedTerritory.owner_nation_id).usual_name}</p>` : '') + "</span>"
            );

            $("#info-details").html(renderDemography(selectedTerritory.stats) + renderProduction(selectedTerritory) + renderLoyalties(selectedTerritory.loyalties));
        }

        function updateOwnerPane() {
            if (selectedTerritory.owner_nation_id) {
                var nation = nationsById.get(selectedTerritory.owner_nation_id);
                var html = "";
                html += `<h1>${nation.formal_name}</h1>`;
                html += `<p>Usual name: ${nation.usual_name}<p>`;
                html += renderNationFlagSection(nation);
                html += renderDemography(nation.stats);
                $("#owner-details").html(html);
            }
            else {
                $("#owner-details").html("<h1><b>Neutral territory</b></h1>");
            }
        }

        function renderRanking(ranking) {
            return '<div>'
                + `<b>${ranking.title}</b><br>`
                + `<table>`
                + ranking.ranked_nation_ids.map((nationId, index) => `<tr><td class="stat-value">${index + 1}</td><td>${nationsById.get(nationId).usual_name}</td><td class="stat-value">${formatValue(ranking.data[index], ranking.data_unit)}</td></tr>`).join("")
                + `</table>`
                + '</div>';
        }

        function updateRankingsPane() {
            $('#rankings-display').html(allRankings.map(ranking => renderRanking(ranking)).join(""));
        }

        function updateNationPane(nation, component) {
            if (nation !== undefined) {
                component.html(
                    `<h1>${nation.formal_name}</h1>`
                    + `<p>Usual name: ${nation.usual_name}<p>`
                );
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
                        ? `<tr><td>${item.description}</td><td><i>${formatValue(budget[key][ResourceType.Capital], StatUnit.DecimalNumber)}</i></td></tr>`
                        : `<tr><td>${item.description}</td><td><i style="color: crimson">${formatValue(budget[key][ResourceType.Capital], StatUnit.DecimalNumber)}</i></td></tr>`;
                }).join("")
                + '</table>'
            );

            $('#resource-bar').html(Object.keys(budget.stockpiles).map(key => {
                let resourceTypeInfo = resourceTypeInfoByType.get(key);
                let balance = resourceTypeInfo.can_be_stocked ? budget.balances[key] : -budget.expenses[key];
                let formattedStockpile = resourceTypeInfo.can_be_stocked ? budget.stockpiles[key].toFixed(2) : "(can't be stockpiled)";
                let formattedBalance = balance < 0
                        ? `(<span class="deficit-balance">${balance.toFixed(2)}</span>)`
                        : `(<span class="surplus-balance">+${balance.toFixed(2)}</span>)`;
                return '<div class="resource-box">'
                    + `${budget.available_production[key].toFixed(2)} <img class="resource-icon" src="res/bundled/icons/resource_${key.toLowerCase()}.png" title="${resourceTypeInfo.description}&#10;&#10;Production: ${budget.production[key].toFixed(2)}&#10;Stockpile: ${formattedStockpile}&#10;Upkeep: -${budget.upkeep[key].toFixed(2)}&#10;Expenses: -${budget.expenses[key].toFixed(2)}&#10;Available: ${budget.available_production[key].toFixed(2)}"> ${formattedBalance}`
                    + '</div>'
            }));
        }

        function renderVictorIcon() {
            return '<img class="victor-icon" src="res/bundled/icons/crown.png" title="Goal winner">';
        }

        function updateVictoryPane() {
            $('#victory-details').html(
                (victoryStatus.victory_status == VictoryStatus.HasBeenWon ? `<h1>${nationsById.get(victoryStatus.winner_nation_id).usual_name} has won!</h1>` + renderNationFlagFullSize(nationsById.get(victoryStatus.winner_nation_id)) : "")
                + victoryStatus.goals.map((goal, index) =>
                    `<h4>Goal: ${goal.title}</h4>`
                    + '<table><tr><th>Rank</th><th>Nation</th><th>Progression</th></tr>'
                    + victoryStatus.progressions[index].map(nationProgress => `<tr><td>${nationProgress.rank}</td><td>${(nationProgress.is_fulfilled && nationProgress.rank == 1 ? renderVictorIcon() : "") + nationsById.get(nationProgress.nation_id).usual_name}</td><td>${formatValue(nationProgress.value, goal.unit)} / ${formatValue(goal.goal, goal.unit)} (${formatValue(nationProgress.progress, StatUnit.Percent)})</td></tr>`).join("")
                    + '</table>'
                ).join("")
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
                    + `<ul>${deployments.map(d => `<li>${divisionTypeInfoByType.get(d.division_type).description} on <a href="javascript:void(0)" onclick="selectTerritory(${d.territory_id})">${territoriesById.get(d.territory_id).name}</a> - <a href="javascript:void(0)" onclick="cancelDeployment(${d.deployment_id})">cancel</a></li>`).join("")}</ul>`
                    + '</p>';
            }
            else {
                html += "<p>No confirmed deployments for now.</p>"
            }

            return html;
        }

        function getRemainingDeployments(typeToDeploy) {
            let availableResources = { ...budget.available_production };

            pendingDeployments.forEach(d => {
                deploymentDivisionType = divisionTypeInfoByType.get(d.divisionType);
                Object.keys(deploymentDivisionType.deployment_costs).forEach(resource => availableResources[resource] -= deploymentDivisionType.deployment_costs[resource]);
            });

            divisionType = divisionTypeInfoByType.get(typeToDeploy);

            return Object.keys(divisionType.deployment_costs).reduce((min, resource) => Math.min(Math.floor(availableResources[resource] / divisionType.deployment_costs[resource]), min), Number.MAX_SAFE_INTEGER);
        }

        function renderDivisionTypeRemarks(divisionTypeInfo) {
            remarks = [];

            if (!divisionTypeInfo.can_take_territory) {
                remarks.push("Won't capture a territory if last unit standing");
            }

            if (divisionTypeInfo.can_fly) {
                remarks.push("Will remain in their current territory when done attacking");
            }
            
            return remarks.join("; ");
        }

        function updateDeploymentsPane() {
            var html = "";

            html += '<p>You can still deploy (select the type of division you want to deploy):<br><table>'
            html += '<tr><th>Quantity</th><th>Type</th><th>Attack Power</th><th>Defense Power</th><th>Moves</th><th>Production Cost</th><th>Attack Cost</th><th>Remarks</th></tr>'
            html += divisionTypeInfoByType.values().map(divisionTypeInfo => '<tr>'
                + `<td>${getRemainingDeployments(divisionTypeInfo.division_type)}x</td>`
                + `<td>${renderActionLink(divisionTypeInfo.description, `startDeploying('${divisionTypeInfo.division_type}')`, divisionTypeInfo.division_type == selectedDivisionType)}</td>`
                + `<td class="stat-value">${formatValue(divisionTypeInfo.attack_power / 100, StatUnit.Percent)}</td>`
                + `<td class="stat-value">${formatValue(divisionTypeInfo.defense_power / 100, StatUnit.Percent)}</td>`
                + `<td>${formatValue(divisionTypeInfo.moves, StatUnit.WholeNumber)}</td>`
                + `<td>${renderResourceCosts(mapExportedObject(divisionTypeInfo.deployment_costs))}</td>`
                + `<td class="stat-value">${renderResourceCosts(mapExportedObject(divisionTypeInfo.attack_costs))}</td>`
                + `<td>${renderDivisionTypeRemarks(divisionTypeInfo)}</td>`
                + '</tr>')
                .toArray()
                .join("")
            html += '</table></p>';


            if (pendingDeployments.length > 0) {
                html += '<p>Pending deployments (<a href="javascript:void(0)" onclick="confirmAllPendingDeployment()">confirm all</a> or <a href="javascript:void(0)" onclick="cancelAllPendingDeployment()">cancel all</a>): '
                    + '<p>'
                    + `<ul>${pendingDeployments.map(d => `<li>${divisionTypeInfoByType.get(d.divisionType).description} on <a href="javascript:void(0)" onclick="selectTerritory(${d.territoryId})">${territoriesById.get(d.territoryId).name}</a> - <a href="javascript:void(0)" onclick="removeDeployment(${d.territoryId}, '${d.divisionType}')">cancel</a></li>`).join("")}</ul>`
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

        function getSelectedDivisionsMoves() {
            return Math.min(...getAllSelectedDivisionsInTerritory().map(d => divisionTypeInfoByType.get(d.division_type).moves));
        }

        function canSelectedDivisionsFly() {
            return !getAllSelectedDivisionsInTerritory().some(d => !divisionTypeInfoByType.get(d.division_type).can_fly);
        }

        function territoryAllowsSafePassage(territory) {
            return territory.owner_nation_id == ownNation.nation_id;
        }

        function filterReacheableTerritories(originTerritory, moves, canFly) {
            var territoriesToExplore = [originTerritory.territory_id];
            let reacheableTerritories = new Map();
            var remainingMoves = moves;

            while (moves > 0 && territoriesToExplore.length > 0) {
                moves--;
                let territoriesToExploreNext = [];

                while (territoriesToExplore.length > 0) {
                    let territory = territoriesById.get(territoriesToExplore.pop());

                    if (canFly && territory.terrain_type == TerrainType.Water) {
                        territory.connected_territory_ids.forEach(tid => {
                            if (!reacheableTerritories.has(tid)) {
                                reacheableTerritories.set(tid, tid);
                                territoriesToExploreNext.push(tid);
                            }
                        });
                    }
                    else if (territoryAllowsSafePassage(territory)) {
                        territory.connected_land_territory_ids.forEach(tid => {
                            if (!reacheableTerritories.has(tid)) {
                                reacheableTerritories.set(tid, tid);
                                territoriesToExploreNext.push(tid);
                            }
                        });
                    }
                }

                territoriesToExplore = territoriesToExploreNext;
            }

            return territoriesById.values()
                .filter(t => t.terrain_type != TerrainType.Water && t.territory_id != originTerritory.territory_id)
                .filter(t =>
                    reacheableTerritories.has(t.territory_id)
                    || t.has_sea_access && originTerritory.has_sea_access
                );
        }

        function canAffordAttackWithSelectedDivisions() {
            let costs = calculateResourceAttackConsomption(getAllSelectedDivisionsInTerritory());
            let costsOfSelectedDivisions = calculateResourceAttackConsomption(getAllSelectedDivisionsInTerritory().filter(d => d.order && d.order.order_type == OrderType.Attack));
            let netCosts = new Map();

            costs
                .keys()
                .forEach(resourceType => netCosts.set(resourceType, costs.get(resourceType) - costsOfSelectedDivisions.get(resourceType)));

            return !netCosts
                .keys()
                .some(resourceType => netCosts.get(resourceType) > budget.available_production[resourceType]);
        }

        function renderProductionResourceIcon(resourceType) {
            return `<img class="division-resource-icon" src="res/bundled/icons/resource_${resourceType.toLowerCase()}.png" title="${resourceTypeInfoByType.get(resourceType).description}">`;
        }

        function renderResourceCosts(costs) {
            return costs
                .keys()
                .filter(resourceType => costs.get(resourceType) > 0)
                .map(resourceType => `${costs.get(resourceType)}x <img class="division-resource-icon" src="res/bundled/icons/resource_${resourceType.toLowerCase()}.png" title="${resourceTypeInfoByType.get(resourceType).description}">`)
                .toArray()
                .join(" ");
        }

        function calculateResourceAttackConsomption(divisions) {
            let costByResourceType = new Map();

            resourceTypeInfoByType.values().forEach(info => {
                costByResourceType.set(info.resource_type, divisions.map(d => {
                    let costs = divisionTypeInfoByType.get(d.division_type).attack_costs;

                    return costs[info.resource_type] === undefined ? 0 : costs[info.resource_type];
                }).reduce((sum, c) => sum + c, 0))
            });

            return costByResourceType;
        }

        function onDivisionSelectionChange() {
            let numberOfSelectedDivisions = getAllSelectedDivisionsInTerritory().length;
            if (numberOfSelectedDivisions > 0) {
                let renderedCosts = renderResourceCosts(calculateResourceAttackConsomption(getAllSelectedDivisionsInTerritory()));
                let attackLink = canAffordAttackWithSelectedDivisions()
                    ? `${renderActionLink('attack', 'selectAttackTargetForDivisions()')} ${renderedCosts.length > 0 ? `(${renderedCosts})` : ''}`
                    : `(too costly to attack: ${renderedCosts})`;

                $("#send-order-link").html([MapMode.SelectMoveDestinationTerritory, MapMode.SelectAttackTargetTerritory].includes(currentMapMode)
                    ? `Select the destination territory to move to attack with the ${numberOfSelectedDivisions} selected divisions or <a href="javascript:void(0)" onclick="cancelSelectDestinationForDivisions()">cancel</a>`
                    : `With ${numberOfSelectedDivisions} selected divisions (max range is ${getSelectedDivisionsMoves()}): <a href="javascript:void(0)" onclick="selectMoveDestinationForDivisions()">move</a> - ${attackLink} - <a href="javascript:void(0)" onclick="sendDisbandOrdersToSelectedDivisions()">disband</a>`
                );
            }
            else {
                $("#send-order-link").html("&nbsp;");
            }

            if ([MapMode.SelectMoveDestinationTerritory, MapMode.SelectAttackTargetTerritory].includes(currentMapMode)) {
                $("#select-divisions-links").html("");
            }
            else {
                $("#select-divisions-links").html(` (<a href="javascript:void(0)" onclick="selectAllDivisionsInTerritory(true)">select</a> / <a href="javascript:void(0)" onclick="selectAllDivisionsInTerritory(false)">deselect</a> all)`);
            }
        }

        function describeOrder(order) {
            if (order.order_type == OrderType.Move || order.order_type == OrderType.Attack) {
                let destination = territoriesById.get(order.destination_territory_id);
                let destinationLink = renderActionLink(`${destination.name} (${destination.owner_nation_id ? nationsById.get(destination.owner_nation_id).usual_name : "neutral"})`, `selectTerritory(${destination.territory_id})`);

                if (order.order_type == OrderType.Move) {
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

        function selectAttackTargetForDivisions() {
            [...document.getElementById('territory-division-list').getElementsByTagName("input")].forEach(cb => cb.disabled = true);
            setMapMode(MapMode.SelectAttackTargetTerritory);
            onDivisionSelectionChange();
        }

        function selectMoveDestinationForDivisions() {
            [...document.getElementById('territory-division-list').getElementsByTagName("input")].forEach(cb => cb.disabled = true);
            setMapMode(MapMode.SelectMoveDestinationTerritory);
            onDivisionSelectionChange();
        }

        function cancelSelectDestinationForDivisions() {
            setMapMode(MapMode.Default);
            [...document.getElementById('territory-division-list').getElementsByTagName("input")].forEach(cb => cb.disabled = false);
            onDivisionSelectionChange();
        }

        function sendDisbandOrdersToSelectedDivisions() {
            if (!readyStatus.is_game_ready) {
                return;
            }
            let selectedDivisions = getAllSelectedDivisionsInTerritory();
            services.sendDisbandOrders({orders: selectedDivisions.map(d => ({ division_id: d.division_id }))})
                .then(response => patchOrders(response.data))
                .catch(error => {
                    $("#error_messages").html(`<li style="color: crimson">${JSON.stringify(error.responseJSON)}}</li>`);
                });
        }

        function sendMoveOrderToSelectedDivisions(tid) {
            if (!readyStatus.is_game_ready) {
                return;
            }
            setMapMode(MapMode.Default);
            let selectedDivisions = getAllSelectedDivisionsInTerritory();
            services.sendMoveOrders({orders: selectedDivisions.map(d => ({ division_id: d.division_id, destination_territory_id: tid }))})
                .then(response => patchOrders(response.data))
                .then(refreshBudget)
                .catch(error => {
                    $("#error_messages").html(`<li style="color: crimson">${JSON.stringify(error.responseJSON)}}</li>`);
                });
        }

        function cancelOrder(did) {
            if (!readyStatus.is_game_ready) {
                return;
            }
            if ([MapMode.SelectMoveDestinationTerritory, MapMode.SelectAttackTargetTerritory].includes(currentMapMode)) {
                return;
            }
            services.cancelOrders({
                division_ids: [did]
            })
            .then(() => {
                divisionsById.get(did).order = null;
                updateDivisionsPane();
            })
            .then(refreshBudget)
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
            storeSelectedDetailsTabToStorage(selectedDetailsTab);
            updateDetailsTabs();
            selectMainTab(null);
            let detailsPanes = $("#details-panes > div");
            detailsPanes.hide();
            $(`#${DetailsTabs[pane].id}-details`).show();
            if (selectedDetailsTab == 'owner') {
                $("#territory-info-owner").hide();
            }
            else {
                $("#territory-info-owner").show();
            }
            setMapMode(MapMode.QueryTerritory)
        }

        function updateDetailsTabs() {
            $("#details-tabs").html(Object.keys(DetailsTabs).map(pane => renderActionLink(pane, `selectDetailsPane('${pane}')`, pane == selectedDetailsTab)).join(" "));
        }

        function renderActionLink(title, onclick, selected = false) {
            return `<a class="${selected ? "selected-action-link" : "action-link"}" href="javascript:void(0)" onclick="${onclick}">${title}</a>`;
        }

        function stopDeploying() {
            setMapMode(MapMode.Default);
        }

        function startDeploying(divisionType) {
            selectedDivisionType = divisionType;
            updateDeploymentsPane();
            setMapMode(MapMode.DeployDivisions);
        }

        function addDeployment(tid) {
            if (getRemainingDeployments(selectedDivisionType) < 1) {
                return;
            }

            pendingDeployments.push({ divisionType: selectedDivisionType, territoryId: tid });
            updateDeploymentsPane();
            mapDisplay.refresh();
        }

        function removeDeployment(tid, divisionType) {
            var index = pendingDeployments.findIndex(d => d.territoryId == tid && d.divisionType == divisionType);
            if (index === -1) {
                index = pendingDeployments.findIndex(d => d.territoryId == tid);
            }
            if (index !== -1) {
                pendingDeployments.splice(index, 1); // Removes 1 element starting from the found index
            }
            updateDeploymentsPane();
            mapDisplay.refresh();
        }

        function confirmAllPendingDeployment() {
            if (!readyStatus.is_game_ready) {
                return;
            }

            $("#deployments-display").html("<p>Waiting for the server to respond...</p>");
            let deploymentOrders = pendingDeployments.map(d => ({ division_type: d.divisionType, territory_id: d.territoryId }));
            pendingDeployments.length = 0;

            services.deploy({ deployments: deploymentOrders })
                .then(response => response.data.forEach(d => deploymentsById.set(d.deployment_id, d)))
                .then(updateTerritoryDeployments)
                .then(refreshBudget)
                .then(updateDeploymentsPane)
                .then(() => mapDisplay.refresh())
                .catch(error => {
                    $("#error_messages").html(`<li style="color: crimson">${JSON.stringify(error.responseJSON)}}</li>`);
                });
        }

        function cancelDeployment(deploymentId) {
            if (!readyStatus.is_game_ready) {
                return;
            }

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
                    let view = selectedDetailsTab ? DetailsTabs[selectedDetailsTab].mapView : MapView.Political;
                    var highlightMapLayer;
                    var topLayer = (ctx, md) => {
                        if (selectedTerritory) {
                            md.fillTerritory(selectedTerritory, "black");
                            md.labelTerritory(selectedTerritory, "?", "white");
                        }
                    };
                    switch (view) {
                        case MapView.Political: {
                            highlightMapLayer = nationHighlightMapLayer;
                            break;
                        }
                        case MapView.OwnNation: {
                            highlightMapLayer = ownNationHighlightMapLayer;
                            break;
                        }
                        case MapView.Battles: {
                            highlightMapLayer = battlesHighlightMapLayer;
                            topLayer = (ctx, md) => {
                                if (selectedTerritory) {
                                    md.fillTerritory(selectedTerritory, "black");
                                    md.labelTerritory(selectedTerritory, "?", "white");
                                }
                                
                                getTerritoryIdsWithBattles().filter(tid => tid != selectedTerritory.territory_id).forEach(tid => {
                                    md.labelTerritory(territoriesById.get(tid), "!", "white");
                                });
                            };
                            break;
                        }
                        case MapView.Armies: {
                            highlightMapLayer = armiesHighlightMapLayer;
                            break;
                        }
                        case MapView.Deployments: {
                            highlightMapLayer = deploymentsHighlightMapLayer;
                            break;
                        }
                        default: {
                            throw new Error('Unreacheable.');
                        }
                    }
                    mapDisplay.setAllClickable(true);
                    mapDisplay.onClick = selectTerritory;
                    mapDisplay.onContextMenu = undefined;
                    mapDisplay.setLayers([politicalMapLayer, highlightMapLayer]);
                    mapDisplay.setTopLayers([topLayer]);
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
                        removeDeployment(tid, selectedDivisionType);
                    }
                    mapDisplay.setLayers([politicalMapLayer, ownNationHighlightMapLayer]);
                    mapDisplay.setTopLayers([(ctx, md) => {
                        currentDeploymentsByTerritoryId = Map.groupBy(deploymentsById.values(), d => d.territory_id);
                        pendingDeploymentsByTerritoryId = Map.groupBy(pendingDeployments, d => d.territoryId);
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
                case MapMode.SelectMoveDestinationTerritory: {
                    let origin = selectedTerritory;
                    let moves = getSelectedDivisionsMoves();
                    let legalDestinations = filterReacheableTerritories(selectedTerritory, moves, canSelectedDivisionsFly())
                        .filter(t => t.owner_nation_id == ownNation.nation_id)
                        .toArray();
                    territoriesById.values().forEach(t => mapDisplay.setClickable(t.territory_id, legalDestinations.some(dest => dest.territory_id == t.territory_id)));
                    mapDisplay.onClick = sendMoveOrderToSelectedDivisions;
                    mapDisplay.onContextMenu = undefined;
                    mapDisplay.setLayers([politicalMapLayer, (ctx, md) => {
                        legalDestinations
                            .forEach(t => t.owner_nation_id == ownNation.nation_id ? md.fillTerritory(t, "black") : md.fillTerritory(t, "red"));
                    }]);
                    mapDisplay.setTopLayers([]);
                    break;
                }
                case MapMode.SelectAttackTargetTerritory: {
                    let origin = selectedTerritory;
                    let moves = getSelectedDivisionsMoves();
                    let legalDestinations = filterReacheableTerritories(selectedTerritory, moves, canSelectedDivisionsFly())
                        .filter(t => t.owner_nation_id != ownNation.nation_id)
                        .toArray();
                    territoriesById.values().forEach(t => mapDisplay.setClickable(t.territory_id, legalDestinations.some(dest => dest.territory_id == t.territory_id)));
                    mapDisplay.onClick = sendMoveOrderToSelectedDivisions;
                    mapDisplay.onContextMenu = undefined;
                    mapDisplay.setLayers([politicalMapLayer, (ctx, md) => {
                        legalDestinations
                            .forEach(t => t.owner_nation_id == ownNation.nation_id ? md.fillTerritory(t, "black") : md.fillTerritory(t, "red"));
                    }]);
                    mapDisplay.setTopLayers([]);
                    break;
                }
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
                    if (nextTurnHasArrived()) {
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
                    .then(() => {
                        if (nextTurnHasArrived()) {
                            window.location.reload();
                        }
                        else {
                            updateReadyStatus();
                        }
                    });
            }, 1000);
        }

        function stopCheckingForNextTurn() {
            if (refreshReadyStatusInterval) {
                clearInterval(refreshReadyStatusInterval);
            }
        }

        function nextTurnHasArrived() {
            return readyStatus.turn_number != ownNation.turn_number;
        }

        function turnHasExpired() {
            if (!readyStatus.turn_expiration) {
                return false;
            }
            const expirationDate = new Date(readyStatus.turn_expiration);
            const currentDate = new Date();

            return expirationDate < currentDate;
        }

        function updateTimeRemaining() {
            if (!readyStatus.turn_expiration || victoryStatus.victory_status == VictoryStatus.HasBeenWon) {
                $("#time-remaining").html("");
                return;
            }
            const expirationDate = new Date(readyStatus.turn_expiration);
            const currentDate = new Date();

            if (turnHasExpired()) {
                $("#time-remaining").html("Turn expired, upkeep will start soon!");
            }
            else {
                const remaining_ms = expirationDate.getTime() - currentDate.getTime();
                $("#time-remaining").html(`The turn will end in ${renderTime(remaining_ms)}`);
            }
        }

        function startUpdatingTimeRemaining() {
            updateTimeRemaining();
            if (!readyStatus.turn_expiration) {
                $("#time-remaining").html("");
            }
            updatingTimeRemainingInterval = setInterval(() => {
                if (turnHasExpired()) {
                    stopUpdatingTimeRemaining();
                    startCheckingForNextTurn();
                }
                updateTimeRemaining();
            }, 30000);
        }

        function stopUpdatingTimeRemaining() {
            if (updatingTimeRemainingInterval) {
                clearInterval(updatingTimeRemainingInterval);
            }
        }

        function updateReadyStatus() {
            $("#force-next-turn-section").hide();
            $("#ready-for-next-turn-section").hide();
            $("#ready-for-next-turn-status").hide();

            if (victoryStatus.victory_status == VictoryStatus.HasBeenWon) {
                return;
            }
            
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

        let windowTitle = @json(config('app.name', 'Laravel'));
        let flashInterval;
        let flashDelayMiliseconds = 700;

        function startFlash() {
            notificationText = "🔔 " + windowTitle;
            if (flashInterval) {
                clearInterval(flashInterval);
            }
            flashInterval = setInterval(() => {
                document.title = (document.title === windowTitle) ? notificationText : windowTitle;
            }, flashDelayMiliseconds);
        }

        function stopFlash() {
            clearInterval(flashInterval);
            document.title = windowTitle;
        }

        window.addEventListener("load", function() {
            let selectedTerritoryIdFromStorage = getSelectedTerritoryIdFromStorage();
            let selectedMainTabFromStorage = getSelectedMainTabFromStorage();
            let selectedDetailsTabFromStorage = getSelectedDetailsTabFromStorage();
            mergeObjects(ownNation, nationsById.get(ownNation.nation_id), { stats: (o1, o2) => o1.stats = [...o1.stats, ...o2.stats] });
            mapDisplay = new MapDisplay("map-display", territoriesById, md => {
                md.territoryLabeler = t => `${t.name} (${nationsById.has(t.owner_nation_id) ? nationsById.get(t.owner_nation_id).usual_name : "neutral"})`;
                md.addInternationalBorders = true;
                md.setLayers([relationsMapLayer]);
            });
            setMapMode(MapMode.Default);
            updateMainTabs();
            updateDetailsTabs();
            updateRankingsPane();
            updateNationPane(ownNation, $('#own-nation-details'));
            $('#own-nation-flag').html(renderNationFlagSection(ownNation));
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
            startUpdatingTimeRemaining();
            if(selectedDetailsTabFromStorage) {
                selectedDetailsTab = selectedDetailsTabFromStorage;
            }
            if (selectedTerritoryIdFromStorage && territoriesById.has(selectedTerritoryIdFromStorage)) {
                selectTerritory(selectedTerritoryIdFromStorage);
            }
            if (victoryStatus.victory_status == VictoryStatus.HasBeenWon) {
                $('#gameover-message').html(`Game is over, ${nationsById.get(victoryStatus.winner_nation_id).usual_name} is the winner!`);
                selectMainTab(MainTabs.Goals);
            }
            else if (selectedMainTabFromStorage) {
                selectMainTab(selectedMainTabFromStorage);
            }
            window.addEventListener("focus", stopFlash);
            if (!document.hasFocus()) {
                startFlash();
            }
        });
    </script>
    <body>
        <div>
            <p><b>{{ $context->getNation()->getDetail()->getFormalName() }}</b>, turn #{{ $context->getCurrentTurn()->getNumber() }}
            <a href="{{route('logout')}}">logout</a>
            </p>
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
            <span id='time-remaining'></span>
            <span id='gameover-message'></span>
            <x-dev-mode />
        </div>
        <span id="main-tabs"></span>
        <div class="resource-bar" id="resource-bar">
            <div class="resource-box">
                capital
            </div>
            <div class="resource-box">
                food
            </div>
            <div class="resource-box">
                etc.
            </div>
        </div>
        <x-map-display id="map-display" />
        <div id="main">
            <div id="nation-display">
                <div id="own-nation-details">
                    own nation
                </div>
                <div id="own-nation-flag">
                    demographics
                </div>
                <div id="own-nation-demographics">
                    demographics
                </div>
                <h3>Budget</h3>
                <div id="budget-details">
                    budget
                </div>
            </div>
            <div id="battle-logs-display">
                battle logs
            </div>
            <div id="deployments-display">
                deployments
            </div>
            <div id="rankings-display">
                rankings
            </div>
            <div id="goals-display">
                <div id="victory-details">
                victory
                </div>
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
        <div hidden>
            @foreach ($nations as $n)
                @if (!is_null($n->flag_src))
                <img src="{{asset($n->flag_src)}}" id="img_flag_{{$n->nation_id}}">
                @endif
            @endforeach
        </div>
        <div id="asset-info" popover>
            This is the content of the popover.
        </div>
    </body>
</html>
