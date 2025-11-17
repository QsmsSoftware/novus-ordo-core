<?php

namespace App\Models;

use App\Domain\DivisionType;
use App\Domain\OrderType;
use App\Domain\ResourceType;
use App\ModelTraits\ReplicatesForTurns;
use App\ReadModels\OwnedDivisionInfo;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class DivisionDetail extends Model
{
    public const string FIELD_IS_ACTIVE = "is_active";

    use ReplicatesForTurns;

    public function game(): BelongsTo {
        return $this->belongsTo(Game::class);
    }

    public function getGame(): Game {
        return $this->game;
    }

    public function division(): BelongsTo {
        return $this->belongsTo(Division::class);
    }

    public function getDivision(): Division {
        return $this->division;
    }

    public function nation(): BelongsTo {
        return $this->belongsTo(Nation::class);
    }

    public function getNation(): Nation {
        return $this->nation;
    }

    public function territory(): BelongsTo {
        return $this->belongsTo(Territory::class);
    }

    public function getTerritory(): Territory {
        return $this->territory;
    }

    public function accessibleTerritories(): HasMany {
        $territory = $this->getTerritory();
        $accessibleTerritoryIds = $territory->connectedLands()->pluck('connected_territory_id');
        if ($territory->hasSeaAccess()) {
            $accessibleTerritoryIds = $accessibleTerritoryIds->concat($this->getGame()->territories()
                ->where(Territory::whereIsControllable())
                ->where(Territory::whereHasSeaAccess())
                ->whereNot('id', $territory->getId())
                ->pluck('id')
            );
        }

        return $this->getGame()->territories()->whereIn('id', $accessibleTerritoryIds);
    }

    public function getOrderOrNull(): ?Order {
        return $this->getDivision()->orders()
            ->where('turn_id', $this->turn_id)
            ->first();
    }

    public function getOrder(): Order {
        return $this->getOrderOrNull();
    }

    public static function createRuleValidActiveDivision(Nation $nation): Exists {
        return Rule::exists(DivisionDetail::class, 'division_id')
            ->where(DivisionDetail::whereNation($nation))
            ->where(DivisionDetail::whereTurn($nation->getGame()->getCurrentTurn()))
            ->where(DivisionDetail::whereActive());
    }

    private static function whereTurn(Turn $turn): Closure {
        return fn ($builder) => $builder->where('turn_id', $turn->getId());
    }

    private static function whereNation(Nation $nation): Closure {
        return fn ($builder) => $builder->where('nation_id', $nation->getId());
    }

    private static function whereActive(): Closure {
        return fn ($builder) => $builder->where(DivisionDetail::FIELD_IS_ACTIVE, true);
    }
    
    public function isActive(): bool {
        return $this->is_active;
    }

    public function isMoving(): bool {
        $orderOrNull = $this->getOrderOrNull();

        return ($order = $orderOrNull??false)
            && $order->getType() == OrderType::Move;
    }

    public static function getTotalUpkeepCostsByResourceType(Nation $nation, Turn $turn): array {
        $divisionTypes = DB::table('division_details')
            ->where('division_details.nation_id', $nation->getId())
            ->where('division_details.turn_id', $turn->getId())
            ->where('division_details.is_active', true)
            ->join('divisions', 'divisions.id', "=", "division_details.division_id")
            ->pluck('divisions.' . Division::FIELD_DIVISION_TYPE)
            ->map(fn (int $type) => DivisionType::from($type));

        return DivisionType::calculateTotalUpkeepCostsByResourceType(...$divisionTypes);
    }
    
    public function isAttacking(): bool {
        $orderOrNull = $this->getOrderOrNull();

        return ($order = $orderOrNull??false)
            && $order->getType() == OrderType::Attack;
    }

    public function exportForOwner(): OwnedDivisionInfo {
        $division = $this->getDivision();
        return new OwnedDivisionInfo(
            division_id: $division->getId(),
            nation_id: $this->getNation()->getId(),
            territory_id: $this->getTerritory()->getId(),
            division_type: $division->getDivisionType()->name,
            order: $this->getOrderOrNull()?->exportForOwner(),
        );
    }

    public function onNextTurn(DivisionDetail $current): void {
        $this->save();
    }

    public function moveTo(Territory $territory): void {
        $this->territory_id = $territory->getId();
        $this->save();
    }

    public function disband(): void {
        $this->is_active = false;
        $this->save();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public static function create(
        Division $division,
        Territory $territory
    ): DivisionDetail {
        $details = new DivisionDetail();

        $details->game_id = $division->getGame()->getId();
        $details->nation_id = $division->getNation()->getId();
        $details->division_id = $division->getId();
        $details->territory_id = $territory->getId();
        $details->is_active = true;
        $details->turn_id = Turn::getCurrentForGame($division->getNation()->getGame())->getId();
        $details->save();

        return $details;
    }
}
