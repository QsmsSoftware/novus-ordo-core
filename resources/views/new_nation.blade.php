<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
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
        function mapExportedArray(exportedArray, keyFactory) {
            let map = new Map();

            exportedArray.forEach(value => map.set(keyFactory(value), value));

            return map;
        }

        function clearSelection() {
            selectedTerritoriesIds.length = 0;
            updateSelectableTerritories();
            updateMap();
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
            updateMap();
        }

        function updateSelectableTerritories() {
            if (selectedTerritoriesIds.length == 0) {
                selectableTerritoriesIds = suitableAsHomeIds.slice();
            }
            else if (selectedTerritoriesIds.length == numberOfHomeTerritories) {
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
        }

        var mapCanvas;
        var mapCanvasContext;

        function fillTerritory(ctx, territory, fillStyle) {
            let previousFillStyle = ctx.fillStyle;
            let previousGlobalAlpha = ctx.globalAlpha;
            ctx.fillStyle = fillStyle;
            ctx.globalAlpha = 0.5;
            mapCanvasContext.fillRect(territory.x * {{ $map_tile_width_px }}, territory.y * {{ $map_tile_height_px }}, {{ $map_tile_width_px }}, {{ $map_tile_height_px }});
            ctx.fillStyle = previousFillStyle;
            ctx.globalAlpha = previousGlobalAlpha;
        }

        function updateMap() {
            mapCanvasContext.drawImage(document.getElementById("map-layer-0"), 0, 0, mapCanvas.width, mapCanvas.height);
            selectableTerritoriesIds.forEach(tid => fillTerritory(mapCanvasContext, territoriesById.get(tid), "green"));
            alreadyTakenIds.forEach(tid => fillTerritory(mapCanvasContext, territoriesById.get(tid), "black"));
            selectedTerritoriesIds.forEach(tid => fillTerritory(mapCanvasContext, territoriesById.get(tid), "blue"));
            mapCanvasContext.drawImage(document.getElementById("map-layer-2"), 0, 0, mapCanvas.width, mapCanvas.height);
        }

        function getTerritoryUnderCursorOrUndefined(cursorX, cursorY) {
            const x = Math.floor(cursorX / {{ $map_tile_width_px }});
            const y = Math.floor(cursorY / {{ $map_tile_height_px }});
            return territoriesById.values().find(t => t.x == x && t.y == y);
        }

        $(document).ready(function(){
            mapCanvas = $("#map-canvas")[0];
            mapCanvasContext = mapCanvas.getContext("2d");
            mapCanvas.addEventListener('click', (event) => {
                const rect = mapCanvas.getBoundingClientRect();
                const x = event.clientX - rect.left;
                const y = event.clientY - rect.top;
                
                selectTerritory(getTerritoryUnderCursorOrUndefined(x, y).territory_id);
            });
            mapCanvas.addEventListener('mousemove', (event) => {
                const rect = mapCanvas.getBoundingClientRect();
                const x = event.clientX - rect.left;
                const y = event.clientY - rect.top;
                let territoryOrUndefined = getTerritoryUnderCursorOrUndefined(x, y);

                mapCanvas.title = territoryOrUndefined === undefined ? "" : territoryOrUndefined.name;
            });
            clearSelection();
            updateMap();
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
                <input type="text" name="name" class="form-control">
                <input type="hidden" name="territory_ids_as_json" id="territory_ids_as_json">
                <br>
                <button class="btn btn-primary" type="submit">Create nation</button>
            </form>
        </div>
        <p>Select your home territory (<span id="pick-message"></span>) <a href="javascript:void(0)" onclick="clearSelection()">clear selection</a></p>
        <canvas id="map-canvas" width="{{ $map_width_px }}" height="{{ $map_height_px }}"></canvas>
        <div hidden>
            <img id="map-layer-0" src="res/map/map_layer_0.png" />
            <img id="map-layer-2" src="res/map/map_layer_2.png" />
        </div>
    </body>
</html>
