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
        var selectedTerritoriesIds = [];
        
        function clearSelection() {
            selectedTerritoriesIds.length = 0;
            $("#selected-ids").html("");
        }
        function selectTerritory(territoryId) {
            if (selectedTerritoriesIds.length >= numberOfHomeTerritories) {
                return;
            }
            if (!suitableAsHomeIds.includes(territoryId)) {
                return;
            }
            selectedTerritoriesIds.push(territoryId);
            $("#territory_ids_as_json").val(JSON.stringify(selectedTerritoriesIds));
            $("#selected-ids").html(JSON.stringify(selectedTerritoriesIds));
        }
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
        <p>Select your home territory (pick {{$number_of_home_territories}} connected territories) <a href="javascript:void(0)" onclick="clearSelection()">clear selection</a></p>
        <span id="selected-ids"></span>
        <?php
            use App\Domain\TerrainType;
            $territoryColorByTerrainTypes = [
                TerrainType::Water->value => '#154360',
                TerrainType::Plain->value => 'DarkKhaki',
                TerrainType::River->value => 'DarkKhaki',
                TerrainType::Desert->value => 'BurlyWood',
                TerrainType::Tundra->value => 'Beige',
                TerrainType::Mountain->value => 'DarkGrey',
                TerrainType::Forest->value => '#145A32',
            ]
        ?>
        <div style="line-height:0;">
            @foreach($territories_by_row_column as $row)
                    @foreach($row as $territory)<div title=@json($territory->getName()) onclick="selectTerritory({{$territory->getId()}})" style="cursor: pointer; margin:0; padding:0;display:inline-block; height:20px; width:30px; background-color: {{$territoryColorByTerrainTypes[$territory->getTerrainType()->value]}}"></div>@endforeach
                    <br>
            @endforeach	
        </div>
    </body>
</html>
