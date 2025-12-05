<?php
namespace App\Services;

use App\Domain\DivisionType;
use App\Domain\LaborPoolConstants;
use App\Domain\MapData;
use App\Domain\OrderType;
use App\Domain\ProductionBidConstants;
use App\Domain\ResourceType;
use App\Domain\StatUnit;
use App\Domain\TerrainType;
use App\Domain\TerritoryStat;
use App\Domain\VictoryStatus;

class JavascriptStaticServicesGenerator {
    public function __construct(
        private readonly JavascriptClientServicesGenerator $servicesGenerator,
    )
    {
        
    }
    
    public function getStaticJsServices(): StaticJavascriptResource {
        return StaticJavascriptResource::permanent('clientservices', fn () => join(PHP_EOL, [
                $this->servicesGenerator->generateClientEnum(ResourceType::class, true),
                $this->servicesGenerator->generateClientEnum(TerrainType::class, true),
                $this->servicesGenerator->generateClientEnum(OrderType::class, true),
                $this->servicesGenerator->generateClientEnum(DivisionType::class, true),
                $this->servicesGenerator->generateClientEnum(StatUnit::class, true),
                $this->servicesGenerator->generateClientEnum(TerritoryStat::class, true),
                $this->servicesGenerator->generateClientEnum(VictoryStatus::class, true),
                $this->servicesGenerator->generateClientConstants(LaborPoolConstants::class),
                $this->servicesGenerator->generateClientConstants(ProductionBidConstants::class),
                $this->servicesGenerator->generateClientService("NovusOrdoServices", "ajax"),
                "const allResourceTypes = " . json_encode(ResourceType::exportMetas()) . ";",
                "const allDivisionTypes = " . json_encode(DivisionType::exportMetas()) . ";",
                "const allTerrainTypes = " . json_encode(TerrainType::exportMetas()) . ";",
                $this->generateMapData(),
        ]));
    }

    private function generateMapData(): string {
        return "const MapData = " . json_encode(new class {
            public int $MapTileWidthPixels = MapData::WIDTH_PIXELS_PER_TILE;
            public int $MapTileHeightPixels = MapData::HEIGHT_PIXELS_PER_TILE;
            public int $MapWidthPixels = MapData::WIDTH * MapData::WIDTH_PIXELS_PER_TILE;
            public int $MapHeightPixels = MapData::HEIGHT * MapData::HEIGHT_PIXELS_PER_TILE;
        });
    }
}