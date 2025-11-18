<?php

namespace App\Models;

use App\ModelTraits\ReplicatesForTurns;
use App\Utils\GuardsForAssertions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NationLoyalty extends Model
{
    use GuardsForAssertions;
    use ReplicatesForTurns;

    public const string FIELD_LOYALTY_RATIO = 'loyalty';

    public static function getLoyaltyRatio(Nation $nation, Territory $territory, Turn $turn): float {
        $loyaltyOrNull = NationLoyalty::getLoyaltyOrNull($nation, $territory, $turn);

        return is_null($loyaltyOrNull) ? 0 : $loyaltyOrNull->loyalty / 100;
    }

    public static function getLoyaltyOrNull(Nation $nation, Territory $territory, Turn $turn): ?NationLoyalty {
        return NationLoyalty::where('nation_id', $nation->getId())
            ->where('territory_id', $territory->getId())
            ->where('turn_id', $turn->getId())
            ->first();
    }

    public static function setLoyaltyRatio(Nation $nation, Territory $territory, Turn $turn, float $loyaltyRatio): void {
        $loyaltyOrNull = NationLoyalty::getLoyaltyOrNull($nation, $territory, $turn);

        if (is_null($loyaltyOrNull)) {
            $loyalty = new NationLoyalty();
            $loyalty->game_id = $nation->getGame()->getId();
            $loyalty->nation_id = $nation->getId();
            $loyalty->territory_id = $territory->getId();
            $loyalty->turn_id = $turn->getId();
        }
        else {
            $loyalty = NationLoyalty::notNull($loyaltyOrNull);
        }

        $loyalty->loyalty = round($loyaltyRatio * 100);
        $loyalty->save();
    }

    public static function setLoyaltyRatioIfNotSet(Nation $nation, Territory $territory, Turn $turn, float $loyaltyRatio): void {
        $loyaltyOrNull = NationLoyalty::getLoyaltyOrNull($nation, $territory, $turn);

        if (is_null($loyaltyOrNull)) {
            NationLoyalty::setLoyaltyRatio($nation, $territory, $turn, $loyaltyRatio);
        }
    }

    public function onNextTurn(Turn $current): void {
        $this->save();
    }

    public function export(): array {
        return ["nation_id" => $this->nation_id, "loyalty" => $this->loyalty / 100];
    }
}
