<?php

namespace App\Models;

use App\ModelTraits\ReplicatesForTurns;
use App\Utils\GuardsForAssertions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class NationTerritoryLoyalty extends Model
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

    public function getNationId(): int {
        return $this->nation_id;
    }

    private function isLoyaltyOfOwner(): bool {
        return DB::table('territory_details')
            ->where('territory_details.territory_id', $this->territory_id)
            ->where('territory_details.turn_id', $this->turn_id)
            ->where('territory_details.owner_nation_id', $this->nation_id)
            ->exists();
    }

    public static function getLoyaltyRatioForNation(Nation $nation, Territory $territory, Turn $turn): float {
        return NationTerritoryLoyalty::where('nation_id', $nation->getId())
            ->where('territory_id', $territory->getId())
            ->where('turn_id', $turn->getId())
            ->value('loyalty') / 100;
    }

    public function getLoyaltyRatio(): float {
        return $this->loyalty / 100;
    }
    
    public function setLoyaltyRatio(float $loyaltyRatio): void {
        $this->loyalty = max(0, min(100, round($loyaltyRatio * 100)));
        $this->save();
    }

    public static function getLoyaltyOrNull(Nation $nation, Territory $territory, Turn $turn): ?NationTerritoryLoyalty {
        return NationTerritoryLoyalty::where('nation_id', $nation->getId())
            ->where('territory_id', $territory->getId())
            ->where('turn_id', $turn->getId())
            ->first();
    }

    public static function create(Nation $nation, Territory $territory, Turn $turn, float $initialLoyaltyRatio): void {
            $loyalty = new NationTerritoryLoyalty();
            $loyalty->game_id = $nation->getGame()->getId();
            $loyalty->nation_id = $nation->getId();
            $loyalty->territory_id = $territory->getId();
            $loyalty->turn_id = $turn->getId();
            $loyalty->loyalty = round($initialLoyaltyRatio * 100);
            $loyalty->save();
    }

    public static function setLoyaltyRatioIfNotSet(Nation $nation, Territory $territory, Turn $turn, float $loyaltyRatio): void {
        $loyaltyOrNull = NationTerritoryLoyalty::getLoyaltyOrNull($nation, $territory, $turn);

        if (is_null($loyaltyOrNull)) {
            NationTerritoryLoyalty::create($nation, $territory, $turn, $loyaltyRatio);
        }
    }

    private function decay(): void {
        $this->setLoyaltyRatio($this->getLoyaltyRatio() * NationTerritoryLoyalty::RIVAL_LOYALTY_DECAY);
        $this->save();
    }

    public function onNextTurn(NationTerritoryLoyalty $current, float $territoryTotalLoyalty): void {
        if ($this->isLoyaltyOfOwner()) {
            $ownerLoyalty = $current->getLoyaltyRatio();
            $ownerLoyaltyWeight = $territoryTotalLoyalty > 0 ? $ownerLoyalty / $territoryTotalLoyalty : 1;
            $loyaltyGain = min(1.00 - $territoryTotalLoyalty, NationTerritoryLoyalty::BASE_LOYALTY_GAIN * ((1 - NationTerritoryLoyalty::RIVAL_LOYALTY_IMPACT) + NationTerritoryLoyalty::RIVAL_LOYALTY_IMPACT * $ownerLoyaltyWeight));
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
