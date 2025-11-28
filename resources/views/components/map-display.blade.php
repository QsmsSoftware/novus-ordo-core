@php
    use App\Domain\MapData;
    // $map_tile_width_px = MapData::WIDTH_PIXELS_PER_TILE;
    // $map_tile_height_px = MapData::HEIGHT_PIXELS_PER_TILE;
    $map_width_px = MapData::WIDTH * MapData::WIDTH_PIXELS_PER_TILE;
    $map_height_px = MapData::HEIGHT * MapData::HEIGHT_PIXELS_PER_TILE;
@endphp
@once
<script src="js/component-map-display.js"></script>
@endonce
<div class=@json($class??"") id=@json($id)>
    <canvas id=@json($id . "-canvas") width="{{ $map_width_px }}" height="{{ $map_height_px }}"></canvas>
    <div hidden>
        <img id=@json($id . "-map-layer-0") src="res/bundled/map/map_layer_0.png" />
        <img id=@json($id . "-map-layer-2") src="res/bundled/map/map_layer_2.png" />
    </div>
</div>