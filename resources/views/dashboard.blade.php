<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Laravel') }}</title>
        <script src="{{ asset('js/jquery-3.7.1.min.js') }}"></script>
        <style type="text/css">
            /* AI generated. */
            .grid-container {
                display: grid; /* Enables CSS Grid layout */
                grid-template-columns: 1fr 1fr; /* Creates two equal-width columns */
                gap: 20px; /* Adds space between columns */
            }

            .column {
                padding: 15px;
                background-color: #f0f0f0;
                border: 1px solid #ccc;
            }

            /* Optional: Make columns stack on smaller screens */
            @media (max-width: 768px) {
                .grid-container {
                    grid-template-columns: 1fr; /* Changes to a single column layout */
                }
            }
        </style>
    </head>
    <body>
        <script>
            const TerritoryDisplayMode = {
                Normal: 0,
                DeployDivisions: 1,
                SelectDivisions: 2,
                SelectTerritory: 3,
            };

            class PendingDeploymentRequest {
                #_territoryId;

                get territoryId() {
                    return this.#_territoryId;
                }

                #_numberToDeploy = 0;

                get numberToDeploy() {
                    return this.#_numberToDeploy;
                }

                constructor(territoryId) {
                    this.#_territoryId = territoryId;
                }

                increment() {
                    this.#_numberToDeploy++;
                }
            }

            class TerritoryDivisionsSelection {
                #_territoryId;

                get territoryId() {
                    return this.#_territoryId;
                }

                #_numberSelected = 0;

                get numberSelected() {
                    return this.#_numberSelected;
                }

                constructor(territoryId) {
                    this.#_territoryId = territoryId;
                }

                increment() {
                    this.#_numberSelected++;
                }
            }

            var currentTerritoryDisplayMode = TerritoryDisplayMode.Normal;
            let ownNation = @json($ownNation);
            let ownTerritoriesById = exportedToMapWithNumericKeys(@json($ownTerritoriesById));
            let ownDivisionsById = exportedToMapWithNumericKeys(@json($ownDivisionsById));
            let deploymentsById = exportedToMapWithNumericKeys(@json($deploymentsById));
            let nationsById = exportedToMapWithNumericKeys(@json($nationsById));
            let territoriesById = exportedToMapWithNumericKeys(@json($territoriesById));
            let battleLogs = @json($battleLogs);
            let deploymentsByTerritoryId = new Map();
            let pendingDeploymentsByTerritoryId = new Map();
            let selectedDivisionsByTerritoryId = new Map();
            let maxDeployments = @json($max_remaining_deployments);
            var currentDeployments = 0;
            var selectedDivisionIds = [];
            let uriTemplatePostDeploy = @json(route('deployment.store', ['territoryId' => '##territoryId##']));
            let divisionsByTerritoryId = new Map();
            let territoriesByNationId = new Map();
            let idleDivisionsById = new Map();
            
            function exportedToMapWithNumericKeys(exportedObject) {
                let map = new Map();

                Object.entries(exportedObject).forEach(([key, value]) => {
                    map.set(parseInt(key), value);
                })

                return map;
            }

            function selectDivisionFromTerritory(territoryId) {
                if (selectedDivisionsByTerritoryId.has(territoryId)) {
                    request = selectedDivisionsByTerritoryId.get(territoryId);
                }
                else {
                    request = new TerritoryDivisionsSelection(territoryId);
                    selectedDivisionsByTerritoryId.set(territoryId, request)
                }

                request.increment();
                renderOwnTerritories();
            }

            function addDeployment(territoryId) {
                if (pendingDeploymentsByTerritoryId.has(territoryId)) {
                    request = pendingDeploymentsByTerritoryId.get(territoryId);
                }
                else {
                    request = new PendingDeploymentRequest(territoryId);
                    pendingDeploymentsByTerritoryId.set(territoryId, request)
                }

                request.increment();
                currentDeployments++;
                renderOwnTerritories();
            }

            function cancelPendingDeployments() {
                pendingDeploymentsByTerritoryId.clear();
                currentDeployments = 0;
                renderOwnTerritories();
            }
            
            function deployPending() {
                let callChain = Promise.resolve();

                pendingDeploymentsByTerritoryId.forEach(request => {
                    callChain = callChain
                        .then(() => {
                            return $.post({
                                    url: uriTemplatePostDeploy.replace('##territoryId##', request.territoryId),
                                    data: {
                                        _token: @json(csrf_token()),
                                        number_of_divisions: request.numberToDeploy
                                    }
                                })
                        })
                        
                });

                callChain
                    .then(() => window.location.reload())
                    .catch(error => {
                        $("#error_messages").html(`<li style="color: crimson">${JSON.stringify(error.responseJSON)}}</li>`);
                    });
            }

            function cancelAllDeployment() {
                if (deploymentsById.size < 1) {
                    return;
                }
                $.post({
                    url: @json(route('deployment.cancel')),
                    data: {
                        _token: @json(csrf_token()),
                        deployment_ids: deploymentsById.values().map(d => d.deployment_id).toArray()
                    }
                })
                .then(() => window.location.reload())
                .catch(error => {
                    $("#error_messages").html(`<li style="color: crimson">${JSON.stringify(error.responseJSON)}}</li>`);
                });
            }

            function cancelOrder(divisionId) {
                $.post({
                    url: @json(route('division.cancel-orders')),
                    data: {
                        _token: @json(csrf_token()),
                        division_ids: [divisionId]
                    }
                })
                .then(() => window.location.reload())
                .catch(error => {
                    $("#error_messages").html(`<li style="color: crimson">${JSON.stringify(error.responseJSON)}}</li>`);
                });
            }

            function cancelMovingDivisions() {
                selectedDivisionsByTerritoryId.clear();
                selectedDivisionIds = [];
                switchTerritoryDisplayTo(TerritoryDisplayMode.Normal);
            }

            function selectTargetTerritoryForSelectedDivisions() {
                selectedDivisionIds = [];
                selectedDivisionsByTerritoryId.forEach(selection => {
                    let divs = divisionsByTerritoryId.get(selection.territoryId).slice(0, selection.numberSelected);
                    selectedDivisionIds.push(...divs.map(d => d.division_id));
                });

                switchTerritoryDisplayTo(TerritoryDisplayMode.SelectTerritory);
            }

            function selectTerritory(territoryId) {
                $.post({
                    url: @json(route('division.send-move-orders')),
                    data: {
                        _token: @json(csrf_token()),
                        orders: selectedDivisionIds.map(did => ({ division_id: did, destination_territory_id: territoryId }))
                    }
                })
                .then(() => window.location.reload())
                .catch(error => {
                    $("#error_messages").html(`<li style="color: crimson">${JSON.stringify(error.responseJSON)}}</li>`);
                });
            }

            function switchTerritoryDisplayTo(displayMode) {
                if (!Object.values(TerritoryDisplayMode).includes(displayMode)) {
                    alert(`Invalid display mode: ${displayMode}`);
                    return;
                }

                currentTerritoryDisplayMode = displayMode;
                renderOwnTerritories();
                renderActiveDivisions();
                renderOtherTerritories();
                renderBattleLogs();
            }

            function formatTerritoryName(territory) {
                return `${territory.name} (ID ${territory.territory_id})`;
            }

            function renderOwnTerritories() {
                let remainingDeployments = maxDeployments - currentDeployments;
                var html = `Owned territories (${ownTerritoriesById.size}):`;
                html += '<div>';
                html += `You're deploying ${deploymentsById.size} divisions this turn (<a href="javascript:void(0)" onclick="cancelAllDeployment()">cancel</a>).`
                html += ` You can still <a href="javascript:void(0)" onclick="switchTerritoryDisplayTo(TerritoryDisplayMode.DeployDivisions)">deploy</a> ${remainingDeployments} more divisions.`;
                html += ` You can <a href="javascript:void(0)" onclick="switchTerritoryDisplayTo(TerritoryDisplayMode.SelectDivisions)">move or attack</a> with ${idleDivisionsById.size} divisions.`;
                if (currentTerritoryDisplayMode == TerritoryDisplayMode.DeployDivisions) {
                    html += '<div>';
                    html += `<a href="javascript:void(0)" onclick="switchTerritoryDisplayTo(TerritoryDisplayMode.Normal)">Stop</a> deploying divisions.`;
                    html += '</div>';
                }
                if (currentTerritoryDisplayMode == TerritoryDisplayMode.SelectDivisions) {
                    html += '<div>';
                    html += `<a href="javascript:void(0)" onclick="cancelMovingDivisions()">Cancel</a> moving divisions.`;
                    html += '</div>';
                }
                html += '</div>';
                html += '<ul>';
                ownTerritoriesById.forEach(t => {
                    let tid = t.territory_id;
                    html += '<li>'
                    if (currentTerritoryDisplayMode == TerritoryDisplayMode.SelectTerritory) {
                        html += `<a href="javascript:void(0)" onclick="selectTerritory(${tid})">${formatTerritoryName(t)}</a>`;
                    }
                    else {
                        html += formatTerritoryName(t);
                    }
                    if (divisionsByTerritoryId.has(tid)) {
                        let numberOfDivisions = divisionsByTerritoryId.get(tid).length;
                        let numberOfIdleDivisions = divisionsByTerritoryId.get(tid).filter(d => d.order === null).length;
                        let textNumberOfDivisions = `${numberOfDivisions} divisions, ${numberOfIdleDivisions} idle`;
                        if (currentTerritoryDisplayMode == TerritoryDisplayMode.SelectDivisions) {
                            let currentlySelected = selectedDivisionsByTerritoryId.has(tid) ? selectedDivisionsByTerritoryId.get(tid).numberSelected : 0;

                            if (currentlySelected > 0 && currentlySelected < numberOfIdleDivisions) {
                                html += ` ( <a href="javascript:void(0)" onclick="selectDivisionFromTerritory(${tid})">selected ${currentlySelected}</a> of ${numberOfIdleDivisions} total )`;
                            }
                            else if (currentlySelected > 0) {
                                html += ` ( selected ${currentlySelected} of ${numberOfIdleDivisions} total )`;
                            }
                            else if (numberOfIdleDivisions > 0) {
                                html += ` ( <a href="javascript:void(0)" onclick="selectDivisionFromTerritory(${tid})">${textNumberOfDivisions}</a> )`;
                            }
                            else {
                                html += ` ( ${textNumberOfDivisions} )`;
                            }
                        }
                        else {
                            html += ` ( ${textNumberOfDivisions} )`;
                        }
                    }
                    if (deploymentsByTerritoryId.has(tid) > 0) {
                        html += ` [deploying ${deploymentsByTerritoryId.get(tid).length} next turn]`;
                    }
                    if (currentTerritoryDisplayMode == TerritoryDisplayMode.DeployDivisions) {
                        var deployText;
                        if (pendingDeploymentsByTerritoryId.has(tid)) {
                            deployText = `deploying ${pendingDeploymentsByTerritoryId.get(tid).numberToDeploy} extra`;
                        }
                        else if (currentDeployments < maxDeployments) {
                            deployText = 'deploy';
                        }
                        else {
                            deployText = '';
                        }
                        if (currentDeployments < maxDeployments) {
                            html += ` <a href="javascript:void(0)" onclick="addDeployment(${tid})">${deployText}</a>`;
                        }
                        else {
                            html += ` ${deployText}`;
                        }
                    }
                    html += '</li>';
                });
                html += '</ul>';
                if (currentDeployments > 0) {
                    html += '<div>';
                    if (currentTerritoryDisplayMode == TerritoryDisplayMode.DeployDivisions) {
                        html += `With pending: <a href="javascript:void(0)" onclick="deployPending()">deploy ${currentDeployments} divisions</a>, `;
                    }
                    html += `<a href="javascript:void(0)" onclick="cancelPendingDeployments()">cancel pending deployments</a>`;
                    html += '</div>';
                }
                if (currentTerritoryDisplayMode == TerritoryDisplayMode.SelectDivisions && selectedDivisionsByTerritoryId.size > 0) {
                    html += '<div>';
                    html += `<a href="javascript:void(0)" onclick="selectTargetTerritoryForSelectedDivisions()">Select target territory for selected divisions</a>`;
                    html += '</div>';
                }
                if (selectedDivisionIds.length > 0) {
                    html += '<div>';
                    html += `Selected divisions: ${selectedDivisionIds.join(", ")}`;
                    html += '</div>';
                }

                $("#own_territories").html(html);
            }

            function renderActiveDivisions() {
                let numberOfActiveDivisions = ownDivisionsById.size - idleDivisionsById.size;
                var htmlRef = { html: '' };
                htmlRef.html += `<div>Active divisions (${numberOfActiveDivisions} of ${ownDivisionsById.size} total):</div>`;
                if (numberOfActiveDivisions > 0) {
                    htmlRef.html += '<ul>';
                    ownDivisionsById.forEach(d => {
                        if (d.order === null) {
                            return;
                        }
                        htmlRef.html += '<li>';
                        htmlRef.html += `#${d.division_id}`;
                        if (d.order.order_type == 'Move') {
                            let isMoving = ownTerritoriesById.has(d.order.destination_territory_id);

                            htmlRef.html += isMoving ? ' is moving to ' : ' is attacking ';
                            htmlRef.html += formatTerritoryName(territoriesById.get(d.order.destination_territory_id))
                            htmlRef.html += ` <a href="javascript:void(0)" onclick="cancelOrder(${d.division_id})">cancel</a>`;
                        }
                        else {
                            htmlRef.html += 'is following UNKNOWN ORDER';
                        }
                        htmlRef.html += '</li>';
                            
                    });
                    htmlRef.html += '</ul>';
                }

                $("#active_divisions").html(htmlRef.html);
            }

            function renderOtherTerritories() {
                var htmlRef = { html: "" };
                territoriesByNationId.forEach((nationTerritories, nid) => {
                    if (nid === ownNation.nation_id) {
                        return;
                    }
                    let nationName = nid === 0 ? "Neutrals" : nationsById.get(nid).usual_name;
                    htmlRef.html += `<div><b>${nationName} (${nationTerritories.length})</b><br>`;
                    
                    if (currentTerritoryDisplayMode == TerritoryDisplayMode.SelectTerritory) {
                        htmlRef.html += nationTerritories.map(t => `<a href="javascript:void(0)" onclick="selectTerritory(${t.territory_id})">${formatTerritoryName(t)}</a>`).join(", ");
                    }
                    else {
                        htmlRef.html += nationTerritories.map(t => formatTerritoryName(t)).join(", ");
                    }
                });

                $("#other_territories").html(htmlRef.html);
            }

            function renderBattleLogs() {
                var htmlRef = { html: '' };

                if (battleLogs.length == 0) {
                    htmlRef.html = "We didn't participate in any battle this turn.";
                }

                battleLogs.forEach(b => {
                    let destinationTerritory = territoriesById.get(b.territory_id);
                    var summary;
                    if (ownNation.nation_id == b.attacker_nation_id) {
                        if (ownNation.nation_id == b.winner_nation_id) {
                            summary = `<span style="color: green">We conquered ${formatTerritoryName(destinationTerritory)}!</span>`;
                        }
                        else {
                            summary = `<span style="color: red">The attack on ${formatTerritoryName(destinationTerritory)} was repelled!</span>`;
                        }
                    }
                    else {
                        if (ownNation.nation_id == b.winner_nation_id) {
                            summary = `<span style="color: orange">We repelled an attack from ${nationsById.get(b.attacker_nation_id).usual_name} on ${formatTerritoryName(destinationTerritory)}!</span>`;
                        }
                        else {
                            summary = `<span style="color: red">We were defeated on ${formatTerritoryName(destinationTerritory)} and ${nationsById.get(b.attacker_nation_id).usual_name} annexed the territory!</span>`;
                        }
                    }
                    htmlRef.html += '<div>';
                    htmlRef.html += `<b>${summary}</b>`;
                    htmlRef.html += '<pre>';
                    htmlRef.html += b.text;
                    htmlRef.html += '</pre>';
                    htmlRef.html += '</div>';
                });
                
                $("#battle_logs").html(htmlRef.html);
            }

            $(document).ready(function(){
                ownDivisionsById.forEach(d => {
                    if (divisionsByTerritoryId.has(d.territory_id)) {
                        divisionsByTerritoryId.get(d.territory_id).push(d);
                    }
                    else {
                        divisionsByTerritoryId.set(d.territory_id, [d]);
                    }
                    if (d.order === null) {
                        idleDivisionsById.set(d.division_id, d);
                    }
                });
                deploymentsById.values().forEach(d => {
                    if (deploymentsByTerritoryId.has(d.territory_id)) {
                        deploymentsByTerritoryId.get(d.territory_id).push(d);
                    }
                    else {
                        deploymentsByTerritoryId.set(d.territory_id, [d]);
                    }
                });
                territoriesById.values().forEach(t => {
                    let nid = t.owner_nation_id === null ? 0 : t.owner_nation_id;
                    if (territoriesByNationId.has(nid)) {
                        territoriesByNationId.get(nid).push(t);
                    }
                    else {
                        territoriesByNationId.set(nid, [t]);
                    }
                });
                renderOwnTerritories();
                renderActiveDivisions();
                renderOtherTerritories();
                renderBattleLogs();
            });
        </script>
        <div>
            <b>{{$ownNation->usual_name}}, turn #{{$game->turn_number}}</b>
            <a href="{{route('logout')}}">logout</a>
            <x-dev-mode />
        </div>
        <x-error />
        <div class="grid-container">
            <div class="column">
                <div id='own_territories'>
                </div>
                <div id='active_divisions'>
                </div>
                <br>
                <div>
                    <table>
                        @foreach ($budget_items as $field => $b)
                            @if(is_a($b, 'App\Http\Controllers\Asset'))
                                <tr><td>{{$b->description}}</td><td><i>{{$budget->$field}}</i></td></tr>
                            @else
                                <tr><td>{{$b->description}}</td><td><i style="color: crimson">{{$budget->$field}}</i></td></tr>
                            @endif
                        @endforeach
                    </table>
                </div>
                <br>
                <div>
                    Victory progression:
                    <table>
                        @php($i = 1)
                        @foreach ($victory_progresses as $p)
                            <tr><td>{{$i}}.</td><td>{{$nationsById[$p->nationId]->usual_name}}</td><td><i>{{sprintf("%.2f%%", $p->progress * 100)}} (owns {{$p->numberOfTerritories}} of {{$p->numberOfTerritoriesRequired}} required territories)</i></td></tr>
                            @php($i++)
                        @endforeach
                    </table>
                </div>
            </div>
            <div class="column">
                <div id="other_territories">
                </div>
            </div>
        </div>
        <div class="column">
            <div>
                <p><b>Battle logs</b></p>
            </div>
            <div id="battle_logs">
            </div>
        </div>
    </body>
</html>
