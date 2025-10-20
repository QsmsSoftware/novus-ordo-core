<?php
namespace App\Domain;

use App\Utils\Annotations\Description;
use ReflectionEnum;

$terrains[] = array("sprite" => array("res/map/water1", "res/map/water2", "res/map/water3", "res/map/water4", "res/map/water5"), "urban_sprite" => array("res/map/water1", "res/map/water2", "res/map/water3", "res/map/water4", "res/map/water5"), "label" => "Water", "show_attributes" => false, "density" => 0, "movement" => 0, "roughness" => 0, "coast_pop_bonus" => 0, "open" => 1, "economic" => 0);
$terrains[] = array("sprite" => array("res/map/plain1", "res/map/plain2", "res/map/plain3", "res/map/plain4", "res/map/plain5"), "urban_sprite" => array("res/map/plain1", "res/map/plain2", "res/map/plain3", "res/map/plain4", "res/map/plain5"), "label" => "Plain", "show_attributes" => true, "density" => 1, "movement" => 1, "roughness" => 0, "coast_pop_bonus" => 0.25, "open" => 1, "economic" => 1);
$terrains[] = array("sprite" => array("res/map/river"), "urban_sprite" => array("res/map/river_urban"), "label" => "Plain (with major river)", "show_attributes" => true, "density" => 0.8, "movement" => 0.75, "roughness" => 0, "coast_pop_bonus" => 0.25, "open" => 1, "economic" => 1.078125);
$terrains[] = array("sprite" => array("res/map/desert1", "res/map/desert2", "res/map/desert3", "res/map/desert4", "res/map/desert5"), "urban_sprite" => array("res/map/desert1", "res/map/desert2", "res/map/desert3", "res/map/desert4", "res/map/desert5"), "label" => "Desert", "show_attributes" => true, "density" => 0.25, "movement" => 1, "roughness" => 0.75, "coast_pop_bonus" => 1, "open" => 1, "economic" => 1.25);
$terrains[] = array("sprite" => array("res/map/tundra1", "res/map/tundra2", "res/map/tundra3", "res/map/tundra4", "res/map/tundra5"), "urban_sprite" => array("res/map/tundra1", "res/map/tundra2", "res/map/tundra3", "res/map/tundra4", "res/map/tundra5"), "label" => "Tundra", "show_attributes" => true, "density" => 0.25, "movement" => 1, "roughness" => 1, "coast_pop_bonus" => 0.15, "open" => 0.9, "economic" => 1.25);
$terrains[] = array("sprite" => array("res/map/mountain1", "res/map/mountain2", "res/map/mountain3", "res/map/mountain4", "res/map/mountain5"), "urban_sprite" => array("res/map/mountain1", "res/map/mountain2", "res/map/mountain3", "res/map/mountain4", "res/map/mountain5"), "label" => "Mountain", "show_attributes" => true, "density" => 0.25, "movement" => 0.25, "roughness" => 0.60, "coast_pop_bonus" => 1.5, "open" => 0.25, "economic" => 1.25);
$terrains[] = array("sprite" => array("res/map/forest1", "res/map/forest2", "res/map/forest3", "res/map/forest4", "res/map/forest5"), "urban_sprite" => array("res/map/forest1", "res/map/forest2", "res/map/forest3", "res/map/forest4", "res/map/forest5"), "label" => "Forest", "show_attributes" => true, "density" => 0.25, "movement" => 0.5, "roughness" => 0.25, "coast_pop_bonus" => 1.5, "open" => 0.75, "economic" => 1.25);


enum TerrainType :int {
    case Water = 0;
    case Plain = 1;
    #[Description('Plain (with major river)')]
    case River = 2;
    case Desert = 3;
    case Tundra = 4;
    case Mountain = 5;
    case Forest = 6;

    public static function getDescription(TerrainType $value): string {
        $enumInfo = new ReflectionEnum(OrderType::class);

        $caseInfo = $enumInfo->getCase(OrderType::Move->name);

        $attributes = $caseInfo->getAttributes(Description::class);

        if (count($attributes) < 1) {
            return $value->name;
        }

        return reset($attributes)->newInstance()->value;
    }
}