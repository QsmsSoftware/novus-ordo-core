<?php

namespace App\Models;

use App\Utils\GuardsForAssertions;
use Illuminate\Database\Eloquent\Model;

class NationLoyalty extends Model
{
    use GuardsForAssertions;

    public static function getLoyaltyRatio(Nation $nation, Territory $territory, Turn $turn): float {
        $loyaltyOrNull = NationLoyalty::getLoyaltyOrNull($nation, $territory, $turn);

        return is_null($loyaltyOrNull) ? 0 : $loyaltyOrNull->loyalty / 100;
    }

    public static function getLoyaltyOrNull(Nation $nation, Territory $territory, Turn $turn): ?NationLoyalty {
        return NationLoyalty::where('nation_id', $nation)
            ->where('territory_id', $territory->getId())
            ->where('turn_id', $turn->getId());
    }

    public static function setLoyaltyRatio(Nation $nation, Territory $territory, Turn $turn, float $loyaltyRatio): void {
        $loyalty = NationLoyalty::as(NationLoyalty::getLoyaltyOrNull($nation, $territory, $turn), fn () => new NationLoyalty);
        $loyalty->loyalty = round($loyaltyRatio * 100);
        $loyalty->save();
    }
}
