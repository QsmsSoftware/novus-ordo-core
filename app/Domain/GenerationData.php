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

    public static function getTerritoryNames(): array {
        return include(resource_path('generation-data/territory-names.php'));
    }

    public static function getMapData(): MapData {
        ['map' => $map, 'usable' => $usable, 'coasts' => $coasts] = include(resource_path('generation-data/map.php'));

        $territoriesData = [];

        for($x = 0; $x < MapData::WIDTH; $x++) {
            for($y = 0; $y < MapData::HEIGHT; $y++) {
                $terrainType = TerrainType::from($map[$x][$y]);
                $connections = [];

                if (isset($map[$x][$y - 1])) {
                    $connections[] = new TerritoryConnectionData($x, $y - 1, $terrainType != TerrainType::Water && $coasts[$x][$y][GenerationData::Top] != 1);
                }
                if (isset($map[$x + 1][$y - 1])) {
                    $connections[] = new TerritoryConnectionData($x + 1, $y - 1, $terrainType != TerrainType::Water && $coasts[$x][$y][GenerationData::TopRight] != 1);
                }
                if (isset($map[$x + 1][$y])) {
                    $connections[] = new TerritoryConnectionData($x + 1, $y, $terrainType != TerrainType::Water && $coasts[$x][$y][GenerationData::Right] != 1);
                }
                if (isset($map[$x + 1][$y + 1])) {
                    $connections[] = new TerritoryConnectionData($x + 1, $y + 1, $terrainType != TerrainType::Water && $coasts[$x][$y][GenerationData::BottomRight] != 1);
                }
                if (isset($map[$x][$y + 1])) {
                    $connections[] = new TerritoryConnectionData($x, $y + 1, $terrainType != TerrainType::Water && $coasts[$x][$y][GenerationData::Bottom] != 1);
                }
                if (isset($map[$x - 1][$y + 1])) {
                    $connections[] = new TerritoryConnectionData($x - 1, $y + 1, $terrainType != TerrainType::Water && $coasts[$x][$y][GenerationData::BottomLeft] != 1);
                }
                if (isset($map[$x - 1][$y])) {
                    $connections[] = new TerritoryConnectionData($x - 1, $y, $terrainType != TerrainType::Water && $coasts[$x][$y][GenerationData::Left] != 1);
                }
                if (isset($map[$x - 1][$y - 1])) {
                    $connections[] = new TerritoryConnectionData($x - 1, $y - 1, $terrainType != TerrainType::Water && $coasts[$x][$y][GenerationData::TopLeft] != 1);
                }

                // $hasSeaAccess = $terrainType == TerrainType::Water
                //     || (  $map[$x - 1][$y - 1] ?? $map[$x][$y - 1] ?? $map[$x + 1][$y - 1]
                //        ?? $map[$x - 1][$y]                         ?? $map[$x + 1][$y]
                //        ?? $map[$x - 1][$y + 1] ?? $map[$x][$y + 1] ?? $map[$x + 1][$y + 1] ?? null) === TerrainType::Water->value;
                $hasSeaAccess = $terrainType == TerrainType::Water || (array_search(1, $coasts[$x][$y]) !== false);
                $territoriesData[] = new TerritoryData(
                    x: $x,
                    y: $y,
                    terrainType: $terrainType,
                    usableLandRatio: $usable[$x][$y],
                    hasSeaAccess: $hasSeaAccess,
                    connections: $connections,
                );
            }
        }

        return new MapData($territoriesData);
    }
}