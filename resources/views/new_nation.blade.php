<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <title>{{ config('app.name', 'Laravel') }}</title>
        <script src="{{ asset('js/jquery-3.7.1.min.js') }}"></script>
    </head>
    {!! $static_js_services->renderAsTag() !!}
    {!! $static_js_territories_base_info->renderAsTag() !!}
    <script>
        // let services = new NovusOrdoServices(@json(url("")), @json(csrf_token()));

        let numberOfHomeTerritories = {{$number_of_home_territories}};
        let suitableAsHomeIds = @json($suitable_as_home_ids);
        let alreadyTakenIds = @json($already_taken_ids);
        let territoriesById = mapExportedArray(allTerritoriesBaseInfo, t => t.territory_id);
        var selectedTerritoriesIds = [];
        var selectableTerritoriesIds = [];
        var mapDisplay;
        function mapExportedArray(exportedArray, keyFactory) {
            let map = new Map();

            exportedArray.forEach(value => map.set(keyFactory(value), value));

            return map;
        }

        function clearSelection() {
            selectedTerritoriesIds.length = 0;
            updateSelectableTerritories();
            validate();
            mapDisplay.refresh();
            $("#territory_ids_as_json").val(JSON.stringify([]));
            $("#pick-message").html(`select your first territory out of ${numberOfHomeTerritories}`);
        }

        function selectTerritory(territoryId) {
            if (selectedTerritoriesIds.length >= numberOfHomeTerritories) {
                return;
            }
            if (!selectableTerritoriesIds.includes(territoryId)) {
                return;
            }
            selectedTerritoriesIds.push(territoryId);
            $("#territory_ids_as_json").val(JSON.stringify(selectedTerritoriesIds));
            if (selectedTerritoriesIds.length < numberOfHomeTerritories) {
                $("#pick-message").html(`select your next territory, ${numberOfHomeTerritories - selectedTerritoriesIds.length} picks remaining`);
            }
            else {
                $("#pick-message").html("done!");
            }
            updateSelectableTerritories();
            validate();
            mapDisplay.refresh();
        }

        function validate() {
            let selectionCompleted = selectedTerritoriesIds.length == numberOfHomeTerritories;
            let valid = selectionCompleted
                && document.getElementById("nation-name").value.length >= 2
                && document.getElementById("leader-name").value.length >= 2;

            document.getElementById("submit").disabled = !valid;
        }

        function updateSelectableTerritories() {
            let selectionCompleted = selectedTerritoriesIds.length == numberOfHomeTerritories;
            
            if (selectedTerritoriesIds.length == 0) {
                selectableTerritoriesIds = suitableAsHomeIds.slice();
            }
            else if (selectionCompleted) {
                selectableTerritoriesIds = [];
            }
            else {
                selectableTerritoriesIds = [];
                selectedTerritoriesIds.forEach(function(tid) {
                    let territory = territoriesById.get(tid);

                    territory.connected_land_territory_ids.forEach(function (connectedId) {
                        if (!selectedTerritoriesIds.includes(connectedId) && !selectableTerritoriesIds.includes(connectedId) && suitableAsHomeIds.includes(connectedId)) {
                            selectableTerritoriesIds.push(connectedId);
                        }
                    });
                });
            }

            mapDisplay.setAllClickable(false);
            selectableTerritoriesIds.forEach(tid => mapDisplay.setClickable(tid, true));
        }

        $(document).ready(function(){
            document.getElementById("submit").disabled = true;
            document.getElementById("nation-name").addEventListener('input', validate);
            document.getElementById("leader-name").addEventListener('input', validate);
        });

        window.addEventListener("load", function() {
            mapDisplay = new MapDisplay("map-display", territoriesById, md => {
                md.onClick = selectTerritory;
                md.territoryLabeler = t => t.name + (alreadyTakenIds.includes(t.territory_id) ? " (already taken)" : "");
                
                md.setLayers([(ctx, md) => {
                    selectableTerritoriesIds.forEach(tid => md.fillTerritory(territoriesById.get(tid), "green"));
                    alreadyTakenIds.forEach(tid => md.fillTerritory(territoriesById.get(tid), "black"));
                    selectedTerritoriesIds.forEach(tid => md.fillTerritory(territoriesById.get(tid), "blue"));
                }]);
            });

            clearSelection();

            let previousSelectionOrEmpty = @json(old('territory_ids_as_json', ''));
            if (previousSelectionOrEmpty != '') {
                JSON.parse(previousSelectionOrEmpty).forEach(tid => selectTerritory(tid));
                validate();
            }
        });
    </script>
    <body>
        <div>
        <h1>Create your nation</h1>
        </div>
        <br>
        <x-error />
        <div>
            <form method="post" enctype="multipart/form-data" action="{{route('nation.store')}}">
                @csrf
                <h2>Nation</h2>
                <table>
                    <tr>
                        <td>Nation's name:</td>
                        <td><input type="text" id="nation-name" name="nation_name" class="form-control" value="{{ old('nation_name') }}"></td>
                    </tr>
                    <tr>
                        <td>Nation's formal name (a default one will be generated if left blank):</td>
                        <td><input type="text" id="nation-formal-name" name="nation_formal_name" class="form-control" value="{{ old('nation_formal_name') }}"></td>
                    </tr>
                    <tr>
                        <td>Nation's flag (a random flag will be assigned if not provided):</td>
                        <td><input name="nation_flag" type="file" class="form-control"></td>
                    </tr>
                </table>
                <h2>Leader</h2>
                    <table>
                    <tr>
                        <td>Leader's name:</td>
                        <td><input type="text" id="leader-name" name="leader_name" class="form-control" value="{{ old('leader_name') }}"></td>
                    </tr>
                    <tr>
                        <td>Leader's title (Emperor will be used if left blank):</td>
                        <td><input type="text" id="leader-title" name="leader_title" class="form-control" value="{{ old('leader_title') }}"></td>
                    </tr>
                    <tr>
                        <td>Leader's picture (optional):</td>
                        <td><input name="leader_picture" type="file" class="form-control"></td>
                    </tr>
                </table>
                <input type="hidden" name="territory_ids_as_json" id="territory_ids_as_json">
                <br>
                <button id="submit" class="btn btn-primary" type="submit">Create nation</button>
            </form>
        </div>

        <p>Select your home territory (<span id="pick-message"></span>) <a href="javascript:void(0)" onclick="clearSelection()">clear selection</a></p>
        <x-map-display id="map-display" />
    </body>
</html>
