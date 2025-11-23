<?php

namespace App\Domain;

final class GenerationData {
    private const Top = 0;
    private const TopRight = 1;
    private const Right = 2;
    private const BottomRight = 3;
    private const Bottom = 4;
    private const BottomLeft = 5;
    private const Left = 6;
    private const TopLeft = 7;
    private const MinimumUsableLandRatio = 0.20;

    public static function getTerritoryNames(): array {
        return include(resource_path('generation-data/territory-names.php'));
    }

    public static function getMapData(): MapData {
        ['map' => $map, 'usable' => $usable, 'coasts' => $coasts] = include(resource_path('generation-data/map.php'));

        $territoriesData = [];

        for($x = 0; $x < MapData::WIDTH; $x++) {
            for($y = 0; $y < MapData::HEIGHT; $y++) {
                if ($usable[$x][$y] >= GenerationData::MinimumUsableLandRatio) {
                    $usableLandRatio = $usable[$x][$y];
                    $terrainType = TerrainType::from($map[$x][$y]);
                }
                else {
                    $usableLandRatio = 0;
                    $terrainType = TerrainType::Water;
                }
                
                $connections = [];

                if (isset($map[$x][$y - 1])) {
                    $connectedTerrainType = $usable[$x][$y - 1] < GenerationData::MinimumUsableLandRatio ? TerrainType::Water : TerrainType::from($map[$x][$y - 1]);
                    $connections[] = new TerritoryConnectionData($x, $y - 1, isConnectedByLand: $connectedTerrainType != TerrainType::Water && $terrainType != TerrainType::Water && $coasts[$x][$y][GenerationData::Top] != 1);
                }
                if (isset($map[$x + 1][$y - 1])) {
                    $connectedTerrainType = $usable[$x + 1][$y - 1] < GenerationData::MinimumUsableLandRatio ? TerrainType::Water : TerrainType::from($map[$x + 1][$y - 1]);
                    $connections[] = new TerritoryConnectionData($x + 1, $y - 1, isConnectedByLand: $connectedTerrainType != TerrainType::Water && $terrainType != TerrainType::Water && $coasts[$x][$y][GenerationData::TopRight] != 1);
                }
                if (isset($map[$x + 1][$y])) {
                    $connectedTerrainType = $usable[$x + 1][$y] < GenerationData::MinimumUsableLandRatio ? TerrainType::Water : TerrainType::from($map[$x + 1][$y]);
                    $connections[] = new TerritoryConnectionData($x + 1, $y, isConnectedByLand: $connectedTerrainType != TerrainType::Water && $terrainType != TerrainType::Water && $coasts[$x][$y][GenerationData::Right] != 1);
                }
                if (isset($map[$x + 1][$y + 1])) {
                    $connectedTerrainType = $usable[$x + 1][$y + 1] < GenerationData::MinimumUsableLandRatio ? TerrainType::Water : TerrainType::from($map[$x + 1][$y + 1]);
                    $connections[] = new TerritoryConnectionData($x + 1, $y + 1, isConnectedByLand: $connectedTerrainType != TerrainType::Water && $terrainType != TerrainType::Water && $coasts[$x][$y][GenerationData::BottomRight] != 1);
                }
                if (isset($map[$x][$y + 1])) {
                    $connectedTerrainType = $usable[$x][$y + 1] < GenerationData::MinimumUsableLandRatio ? TerrainType::Water : TerrainType::from($map[$x][$y + 1]);
                    $connections[] = new TerritoryConnectionData($x, $y + 1, isConnectedByLand: $connectedTerrainType != TerrainType::Water && $terrainType != TerrainType::Water && $coasts[$x][$y][GenerationData::Bottom] != 1);
                }
                if (isset($map[$x - 1][$y + 1])) {
                    $connectedTerrainType = $usable[$x - 1][$y + 1] < GenerationData::MinimumUsableLandRatio ? TerrainType::Water : TerrainType::from($map[$x - 1][$y + 1]);
                    $connections[] = new TerritoryConnectionData($x - 1, $y + 1, isConnectedByLand: $connectedTerrainType != TerrainType::Water && $terrainType != TerrainType::Water && $coasts[$x][$y][GenerationData::BottomLeft] != 1);
                }
                if (isset($map[$x - 1][$y])) {
                    $connectedTerrainType = $usable[$x - 1][$y] < GenerationData::MinimumUsableLandRatio ? TerrainType::Water : TerrainType::from($map[$x - 1][$y]);
                    $connections[] = new TerritoryConnectionData($x - 1, $y, isConnectedByLand: $connectedTerrainType != TerrainType::Water && $terrainType != TerrainType::Water && $coasts[$x][$y][GenerationData::Left] != 1);
                }
                if (isset($map[$x - 1][$y - 1])) {
                    $connectedTerrainType = $usable[$x - 1][$y - 1] < GenerationData::MinimumUsableLandRatio ? TerrainType::Water : TerrainType::from($map[$x - 1][$y - 1]);
                    $connections[] = new TerritoryConnectionData($x - 1, $y - 1, isConnectedByLand: $connectedTerrainType != TerrainType::Water && $terrainType != TerrainType::Water && $coasts[$x][$y][GenerationData::TopLeft] != 1);
                }

                $hasSeaAccess = $terrainType == TerrainType::Water || (array_find($connections, fn (TerritoryConnectionData $tcd) => !$tcd->isConnectedByLand) !== null);
                $territoriesData[] = new TerritoryData(
                    x: $x,
                    y: $y,
                    terrainType: $terrainType,
                    usableLandRatio: $usableLandRatio,
                    hasSeaAccess: $hasSeaAccess,
                    connections: $connections,
                );
            }
        }

        return new MapData($territoriesData);
    }
}