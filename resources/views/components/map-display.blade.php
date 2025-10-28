@php
    use App\Domain\MapData;
    $map_tile_width_px = MapData::WIDTH_PIXELS_PER_TILE;
    $map_tile_height_px = MapData::HEIGHT_PIXELS_PER_TILE;
    $map_width_px = MapData::WIDTH * MapData::WIDTH_PIXELS_PER_TILE;
    $map_height_px = MapData::HEIGHT * MapData::HEIGHT_PIXELS_PER_TILE;
@endphp
@once
<script>
class MapDisplay {
    #containerId;
    #territoriesById;
    #canvas;
    #layerRenderers = [];
    #onClick;
    #onContextMenu;
    #metadataByTerritoryId;
    #territoryLabeler;
    #addInternationalBorders = false;
    #selectedTerritory = null;

    constructor(containerId, territoriesById, config) {
        this.#containerId = containerId;
        this.#territoriesById = territoriesById;
        this.#canvas = document.getElementById(this.#containerId + "-canvas");
        this.#canvas.addEventListener('click', (event) => {
            const rect = this.#canvas.getBoundingClientRect();
            const x = event.clientX - rect.left;
            const y = event.clientY - rect.top;
            let territoryId = this.#getTerritoryUnderCursorOrUndefined(x, y).territory_id;
            
            if (this.#onClick && this.#metadataByTerritoryId.get(territoryId).clickable) {
                this.#onClick(territoryId, event);
            }
        });
        this.#canvas.addEventListener('contextmenu', (event) => {
            const rect = this.#canvas.getBoundingClientRect();
            const x = event.clientX - rect.left;
            const y = event.clientY - rect.top;
            let territoryId = this.#getTerritoryUnderCursorOrUndefined(x, y).territory_id;
            
            if (this.#onContextMenu && this.#metadataByTerritoryId.get(territoryId).clickable) {
                this.#onContextMenu(territoryId, event);
            }
        });
        this.#canvas.addEventListener('mousemove', (event) => {
            const rect = this.#canvas.getBoundingClientRect();
            const x = event.clientX - rect.left;
            const y = event.clientY - rect.top;
            let territoryOrUndefined = this.#getTerritoryUnderCursorOrUndefined(x, y);

            if (territoryOrUndefined === undefined) {
                this.#canvas.title = ""; 
            }
            else {
                this.#canvas.title = this.#territoryLabeler(territoryOrUndefined);
                this.#canvas.style.cursor = this.#metadataByTerritoryId.get(territoryOrUndefined.territory_id).clickable
                    ? "pointer"
                    : "default";
            }
        });
        this.#territoryLabeler = t => t.name;
        this.#metadataByTerritoryId = new Map();
        territoriesById.keys().forEach(tid => this.#metadataByTerritoryId.set(tid, { clickable: true}));
        if (config) {
            config(this);
        }
        this.refresh();
    }

    get mapWidthPixels() {
        return {{ $map_width_px }};
    }

    get mapHeightPixels() {
        return {{ $map_height_px }};
    }

    get tileWidthPixels() {
        return {{ $map_tile_width_px }};
    }

    get tileHeightPixels() {
        return {{ $map_tile_height_px }};
    }

    get canvas() {
        return this.#canvas;
    }

    set onClick(onClickCallback) {
        this.#onClick = onClickCallback;
    }

    set onContextMenu(onContextMenuCallback) {
        this.#onContextMenu = onContextMenuCallback;
    }

    set territoryLabeler(labeler) {
        this.#territoryLabeler = labeler;
    }

    set addInternationalBorders(b) {
        this.#addInternationalBorders = b;
    }

    set selectedTerritory(territory) {
        if (this.#selectedTerritory != territory) {
            this.#selectedTerritory = territory;
            this.refresh();
        }
    }

    addLayer(renderer) {
        this.#layerRenderers.push(renderer);
    }

    setLayers(renderers) {
        this.#layerRenderers = renderers;
    }

    setAllClickable(clickable) {
        this.#metadataByTerritoryId.values().forEach(meta => meta.clickable = clickable);
    }

    setClickable(territoryId, clickable) {
        this.#metadataByTerritoryId.get(territoryId).clickable = clickable;
    }

    fillTerritory(territory, fillStyle, text) {
        let ctx = this.#canvas.getContext("2d");
        let previousFillStyle = ctx.fillStyle;
        let previousGlobalAlpha = ctx.globalAlpha;
        ctx.fillStyle = fillStyle;
        ctx.globalAlpha = 0.5;
        ctx.fillRect(territory.x * {{ $map_tile_width_px }}, territory.y * {{ $map_tile_height_px }}, {{ $map_tile_width_px }}, {{ $map_tile_height_px }});
        if (text) {
            ctx.fillStyle = "white";
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(text, (territory.x + 0.5) * {{ $map_tile_width_px }}, (territory.y + 0.5) * {{ $map_tile_height_px }});
        }
        ctx.fillStyle = previousFillStyle;
        ctx.globalAlpha = previousGlobalAlpha;
    }

    #getTerritoryByCoordinates(x, y) {
        return this.#territoriesById.values().find(t => t.x == x && t.y == y);
    }

    #getTerritoryUnderCursorOrUndefined(cursorX, cursorY) {
        const x = Math.floor(cursorX / {{ $map_tile_width_px }});
        const y = Math.floor(cursorY / {{ $map_tile_height_px }});
        return this.#getTerritoryByCoordinates(x, y);
    }

    #drawInternationalBorders(ctx) {
        let previousGlobalAlpha = ctx.globalAlpha;
        ctx.globalAlpha = 0.33;
        this.#territoriesById.values().forEach(t => {
            if (!t.owner_nation_id) {
                return;
            }
            var connectedTerritory;
            if (connectedTerritory = this.#getTerritoryByCoordinates(t.x, t.y - 1)) {
                // Top
                if (connectedTerritory.owner_nation_id && t.owner_nation_id != connectedTerritory.owner_nation_id) {
                    ctx.beginPath();
                    ctx.moveTo(t.x * {{ $map_tile_width_px }}, t.y * {{ $map_tile_height_px }});
                    ctx.lineTo((t.x + 1) * {{ $map_tile_width_px }}, t.y * {{ $map_tile_height_px }});
                    ctx.stroke();
                }
            }
            if (connectedTerritory = this.#getTerritoryByCoordinates(t.x + 1, t.y)) {
                // Right
                if (connectedTerritory.owner_nation_id && t.owner_nation_id != connectedTerritory.owner_nation_id) {
                    ctx.beginPath();
                    ctx.moveTo((t.x + 1) * {{ $map_tile_width_px }}, t.y * {{ $map_tile_height_px }});
                    ctx.lineTo((t.x + 1) * {{ $map_tile_width_px }}, (t.y + 1) * {{ $map_tile_height_px }});
                    ctx.stroke();
                }
            }
            if (connectedTerritory = this.#getTerritoryByCoordinates(t.x, t.y + 1)) {
                // Bottom
                if (connectedTerritory.owner_nation_id && t.owner_nation_id != connectedTerritory.owner_nation_id) {
                    ctx.beginPath();
                    ctx.moveTo(t.x * {{ $map_tile_width_px }}, (t.y + 1) * {{ $map_tile_height_px }});
                    ctx.lineTo((t.x + 1) * {{ $map_tile_width_px }}, (t.y + 1) * {{ $map_tile_height_px }});
                    ctx.stroke();
                }
            }
            if (connectedTerritory = this.#getTerritoryByCoordinates(t.x - 1, t.y)) {
                // Left
                if (connectedTerritory.owner_nation_id && t.owner_nation_id != connectedTerritory.owner_nation_id) {
                    ctx.beginPath();
                    ctx.moveTo(t.x * {{ $map_tile_width_px }}, t.y * {{ $map_tile_height_px }});
                    ctx.lineTo(t.x * {{ $map_tile_width_px }}, (t.y + 1) * {{ $map_tile_height_px }});
                    ctx.stroke();
                }
            }
        });
        ctx.globalAlpha = previousGlobalAlpha;
    }

    refresh() {
        let ctx = this.#canvas.getContext("2d");
        ctx.drawImage(document.getElementById(this.#containerId +  "-map-layer-0"), 0, 0, this.#canvas.width, this.#canvas.height);
        this.#layerRenderers.forEach(renderer => renderer(ctx, this));
        if (this.#addInternationalBorders) {
            this.#drawInternationalBorders(ctx);
        }
        ctx.drawImage(document.getElementById(this.#containerId +  "-map-layer-2"), 0, 0, this.#canvas.width, this.#canvas.height);
        if (this.#selectedTerritory) {
            this.fillTerritory(this.#selectedTerritory, "black", "?");
        }
    }
}
</script>
@endonce
<div id=@json($id)>
    <canvas id=@json($id . "-canvas") width="{{ $map_width_px }}" height="{{ $map_height_px }}"></canvas>
    <div hidden>
        <img id=@json($id . "-map-layer-0") src="res/map/map_layer_0.png" />
        <img id=@json($id . "-map-layer-2") src="res/map/map_layer_2.png" />
    </div>
</div>