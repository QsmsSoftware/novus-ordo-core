<?php

namespace App\Models;

use App\ModelTraits\ReplicatesForTurns;
use App\Utils\GuardsForAssertions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class NationLoyalty extends Model
{
    use GuardsForAssertions;
    use ReplicatesForTurns;

    public const string FIELD_LOYALTY = 'loyalty';

    private const float BASE_LOYALTY_GAIN = 0.10;
    private const float RIVAL_LOYALTY_IMPACT = 0.50;
    private const float RIVAL_LOYALTY_DECAY = 0.95;

    public function territory(): BelongsTo {
        return $this->belongsTo(Territory::class);
    }

    private function isLoyaltyOfOwner(): bool {
        return DB::table('territory_details')
            ->where('territory_details.territory_id', $this->territory_id)
            ->where('territory_details.turn_id', $this->turn_id)
            ->where('territory_details.owner_nation_id', $this->nation_id)
            ->exists();
    }

    public function getLoyaltyRatio(): float {
        return $this->loyalty / 100;
    }
    
    public function setLoyaltyRatio(float $loyaltyRatio): void {
        $this->loyalty = max(0, min(100, round($loyaltyRatio * 100)));
        $this->save();
    }

    public static function getLoyaltyOrNull(Nation $nation, Territory $territory, Turn $turn): ?NationLoyalty {
        return NationLoyalty::where('nation_id', $nation->getId())
            ->where('territory_id', $territory->getId())
            ->where('turn_id', $turn->getId())
            ->first();
    }

    public static function create(Nation $nation, Territory $territory, Turn $turn, float $initialLoyaltyRatio): void {
            $loyalty = new NationLoyalty();
            $loyalty->game_id = $nation->getGame()->getId();
            $loyalty->nation_id = $nation->getId();
            $loyalty->territory_id = $territory->getId();
            $loyalty->turn_id = $turn->getId();
            $loyalty->loyalty = round($initialLoyaltyRatio * 100);
            $loyalty->save();
    }

    public static function setLoyaltyRatioIfNotSet(Nation $nation, Territory $territory, Turn $turn, float $loyaltyRatio): void {
        $loyaltyOrNull = NationLoyalty::getLoyaltyOrNull($nation, $territory, $turn);

        if (is_null($loyaltyOrNull)) {
            NationLoyalty::create($nation, $territory, $turn, $loyaltyRatio);
        }
    }

    private function decay(): void {
        $this->setLoyaltyRatio($this->getLoyaltyRatio() * NationLoyalty::RIVAL_LOYALTY_DECAY);
        $this->save();
    }

    public function onNextTurn(NationLoyalty $current, float $territoryTotalLoyalty): void {
        if ($this->isLoyaltyOfOwner()) {
            $ownerLoyalty = $current->getLoyaltyRatio();
            $ownerLoyaltyWeight = $territoryTotalLoyalty > 0 ? $ownerLoyalty / $territoryTotalLoyalty : 1;
            $loyaltyGain = min(1.00 - $territoryTotalLoyalty, NationLoyalty::BASE_LOYALTY_GAIN * ((1 - NationLoyalty::RIVAL_LOYALTY_IMPACT) + NationLoyalty::RIVAL_LOYALTY_IMPACT * $ownerLoyaltyWeight));
            $this->setLoyaltyRatio($ownerLoyalty + $loyaltyGain);
        }
        else {
            $this->decay();
        }
    }

    public function export(): array {
        return ["nation_id" => $this->nation_id, "loyalty" => $this->loyalty / 100];
    }
}
