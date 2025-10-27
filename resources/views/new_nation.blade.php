<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <title>{{ config('app.name', 'Laravel') }}</title>
        <script src="{{ asset('js/jquery-3.7.1.min.js') }}"></script>
    </head>
    <script>
        {!! $js_client_services !!}
        let services = new NovusOrdoServices(@json(url("")), @json(csrf_token()));

        let numberOfHomeTerritories = {{$number_of_home_territories}};
        let suitableAsHomeIds = @json($suitable_as_home_ids);
        let alreadyTakenIds = @json($already_taken_ids);
        let territoriesById = mapExportedArray(@json($territories), t => t.territory_id);
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
            let valid = selectionCompleted && document.getElementById("name").value.length >= 2

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

                    territory.connected_territory_ids.forEach(function (connectedId) {
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
            document.getElementById("name").addEventListener('input', validate);
        });

        window.addEventListener("load", function() {
            mapDisplay = new MapDisplay("map-display", territoriesById, md => {
                md.onClick = selectTerritory;
                md.territoryLabeler = t => t.name + (alreadyTakenIds.includes(t.territory_id) ? " (already taken)" : "");
                
                md.addLayer((ctx, md) => {
                    selectableTerritoriesIds.forEach(tid => md.fillTerritory(territoriesById.get(tid), "green"));
                    alreadyTakenIds.forEach(tid => md.fillTerritory(territoriesById.get(tid), "black"));
                    selectedTerritoriesIds.forEach(tid => md.fillTerritory(territoriesById.get(tid), "blue"));
                });
            });

            clearSelection();

            let previousSelectionOrEmpty = @json(old('territory_ids_as_json', ''));
            if (previousSelectionOrEmpty != '') {
                JSON.parse(previousSelectionOrEmpty).forEach(tid => selectTerritory(tid));
            }
        });
    </script>
    <body>
        <div>
        <b>Create your nation</b>
        </div>
        <br>
        <x-error />
        <div>
            <form method="post" enctype="multipart/form-data" action="{{route('nation.store')}}">
                @csrf
                Nation's name:
                <input type="text" id="name" name="name" class="form-control" value="{{ old('name') }}">
                <input type="hidden" name="territory_ids_as_json" id="territory_ids_as_json">
                <br>
                <button id="submit" class="btn btn-primary" type="submit">Create nation</button>
            </form>
        </div>
        <p>Select your home territory (<span id="pick-message"></span>) <a href="javascript:void(0)" onclick="clearSelection()">clear selection</a></p>
        <x-map-display id="map-display" />
    </body>
</html>
