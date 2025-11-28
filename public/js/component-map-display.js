class MapDisplay {
    #containerId;
    #territoriesById;
    #canvas;
    #middleLayerRenderers = [];
    #topLayerRenderers = [];
    #onClick;
    #onContextMenu;
    #metadataByTerritoryId;
    #territoryLabeler;
    #addInternationalBorders = false;

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
                event.preventDefault();
                this.#onContextMenu(territoryId);
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

    setLayers(renderers) {
        this.#middleLayerRenderers = renderers;
    }

    setTopLayers(renderers) {
        this.#topLayerRenderers = renderers;
    }

    setAllClickable(clickable) {
        this.#metadataByTerritoryId.values().forEach(meta => meta.clickable = clickable);
    }

    setClickable(territoryId, clickable) {
        this.#metadataByTerritoryId.get(territoryId).clickable = clickable;
    }

    fillTerritory(territory, fillStyle) {
        let ctx = this.#canvas.getContext("2d");
        let previousFillStyle = ctx.fillStyle;
        let previousGlobalAlpha = ctx.globalAlpha;
        ctx.fillStyle = fillStyle;
        ctx.globalAlpha = 0.5;
        ctx.fillRect(territory.x * MapData.MapTileWidthPixels, territory.y * MapData.MapTileHeightPixels, MapData.MapTileWidthPixels, MapData.MapTileHeightPixels);
        ctx.fillStyle = previousFillStyle;
        ctx.globalAlpha = previousGlobalAlpha;
    }

    fillTerritoryWithImage(territory, img) {
        let ctx = this.#canvas.getContext("2d");
        let previousGlobalAlpha = ctx.globalAlpha;
        ctx.globalAlpha = 0.5;
        ctx.drawImage(img, territory.x * MapData.MapTileWidthPixels, territory.y * MapData.MapTileHeightPixels, MapData.MapTileWidthPixels, MapData.MapTileHeightPixels);
        ctx.globalAlpha = previousGlobalAlpha;
    }

    labelTerritory(territory, text, fillStyle) {
        let ctx = this.#canvas.getContext("2d");
        let previousFillStyle = ctx.fillStyle;
        let previousGlobalAlpha = ctx.globalAlpha;
        ctx.fillStyle = fillStyle;
        ctx.globalAlpha = 0.5;

        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(text, (territory.x + 0.5) * MapData.MapTileWidthPixels, (territory.y + 0.5) * MapData.MapTileHeightPixels);

        ctx.fillStyle = previousFillStyle;
        ctx.globalAlpha = previousGlobalAlpha;
    }

    #getTerritoryByCoordinates(x, y) {
        return this.#territoriesById.values().find(t => t.x == x && t.y == y);
    }

    #getTerritoryUnderCursorOrUndefined(cursorX, cursorY) {
        const x = Math.floor(cursorX / MapData.MapTileWidthPixels);
        const y = Math.floor(cursorY / MapData.MapTileHeightPixels);
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
                    ctx.moveTo(t.x * MapData.MapTileWidthPixels, t.y * MapData.MapTileHeightPixels);
                    ctx.lineTo((t.x + 1) * MapData.MapTileWidthPixels, t.y * MapData.MapTileHeightPixels);
                    ctx.stroke();
                }
            }
            if (connectedTerritory = this.#getTerritoryByCoordinates(t.x + 1, t.y)) {
                // Right
                if (connectedTerritory.owner_nation_id && t.owner_nation_id != connectedTerritory.owner_nation_id) {
                    ctx.beginPath();
                    ctx.moveTo((t.x + 1) * MapData.MapTileWidthPixels, t.y * MapData.MapTileHeightPixels);
                    ctx.lineTo((t.x + 1) * MapData.MapTileWidthPixels, (t.y + 1) * MapData.MapTileHeightPixels);
                    ctx.stroke();
                }
            }
            if (connectedTerritory = this.#getTerritoryByCoordinates(t.x, t.y + 1)) {
                // Bottom
                if (connectedTerritory.owner_nation_id && t.owner_nation_id != connectedTerritory.owner_nation_id) {
                    ctx.beginPath();
                    ctx.moveTo(t.x * MapData.MapTileWidthPixels, (t.y + 1) * MapData.MapTileHeightPixels);
                    ctx.lineTo((t.x + 1) * MapData.MapTileWidthPixels, (t.y + 1) * MapData.MapTileHeightPixels);
                    ctx.stroke();
                }
            }
            if (connectedTerritory = this.#getTerritoryByCoordinates(t.x - 1, t.y)) {
                // Left
                if (connectedTerritory.owner_nation_id && t.owner_nation_id != connectedTerritory.owner_nation_id) {
                    ctx.beginPath();
                    ctx.moveTo(t.x * MapData.MapTileWidthPixels, t.y * MapData.MapTileHeightPixels);
                    ctx.lineTo(t.x * MapData.MapTileWidthPixels, (t.y + 1) * MapData.MapTileHeightPixels);
                    ctx.stroke();
                }
            }
        });
        ctx.globalAlpha = previousGlobalAlpha;
    }

    refresh() {
        let ctx = this.#canvas.getContext("2d");
        ctx.drawImage(document.getElementById(this.#containerId +  "-map-layer-0"), 0, 0, this.#canvas.width, this.#canvas.height);
        this.#middleLayerRenderers.forEach(renderer => renderer(ctx, this));
        if (this.#addInternationalBorders) {
            this.#drawInternationalBorders(ctx);
        }
        ctx.drawImage(document.getElementById(this.#containerId +  "-map-layer-2"), 0, 0, this.#canvas.width, this.#canvas.height);
        this.#topLayerRenderers.forEach(renderer => renderer(ctx, this));
    }
}