<?php

namespace App\Models;

use App\Domain\DivisionType;
use App\Domain\OrderType;
use App\ReadModels\DeploymentInfo;
use App\Utils\GuardsForAssertions;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use LogicException;

class Deployment extends Model
{
    use SoftDeletes;
    use GuardsForAssertions;

    public const string FIELD_HAS_BEEN_DEPLOYED = 'has_been_deployed';
    public const int DIVISION_COST = 3;

    public function game() :BelongsTo {
        return $this->belongsTo(Game::class);
    }

    public function getGame() :Game {
        return $this->game;
    }

    public function nation() :BelongsTo {
        return $this->belongsTo(Nation::class);
    }

    public function getNation() :Nation {
        return $this->nation;
    }

    public function territory() :BelongsTo {
        return $this->belongsTo(Territory::class);
    }

    public function getTerritory() :Territory {
        return $this->territory;
    }

    public function turn() :BelongsTo {
        return $this->belongsTo(Turn::class);
    }

    public function getTurn() :Turn {
        return $this->turn;
    }

    public function getId() :int {
        return $this->id;
    }

    public function getDivisionType(): DivisionType {
        return DivisionType::from($this->division_type);
    }

    public function hasBeenDeployed() :int {
        return $this->has_been_deployed;
    }

    // public static function createValidationValidTerritoryForDeployment(Nation $nation): Closure {
    //     return function (string $attribute, int $value, Closure $fail) use ($nation) {
    //         $territoryOrNull = $nation->getDetail()->territories()->find($value);
    //         if (is_null($territoryOrNull)) {
    //             $fail("Nation ID {$nation->getId()} doesn't own Territory ID $value");
    //         }
    //     };
    // }

    public static function createRuleValidDeployment(Nation $nation): Exists {
        return Rule::exists(Deployment::class, 'id')
            ->where('nation_id', $nation->getId())
            ->where('turn_id', $nation->getGame()->getCurrentTurn()->getId())
            ->whereNull('deleted_at')
            ->where(Deployment::FIELD_HAS_BEEN_DEPLOYED, false);
    }

    public function export() :DeploymentInfo {
        return new DeploymentInfo(
            deployment_id: $this->getId(),
            division_type: $this->getDivisionType()->name,
            nation_id: $this->getNation()->getId(),
            territory_id: $this->getTerritory()->getId()
        );
    }

    public function execute() :void {
        Division::create($this);
        $this->has_been_deployed = true;
        $this->save();
    }

    public function cancel() :void {
        if ($this->hasBeenDeployed()) {
            throw new LogicException("Deployment {$this->getId()} can't be cancelled because it has been deployed.");
        }
        $this->delete();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'has_been_deployed' => 'boolean',
        ];
    }

    public static function Create(Nation $nation, DivisionType $type, Territory $territory) :Deployment {
        $deployment = new Deployment();
        $deployment->game_id = $nation->getGame()->getId();
        $deployment->nation_id = $nation->getId();
        $deployment->territory_id = $territory->getId();
        $deployment->turn_id = Turn::getCurrentForGame($nation->getGame())->getId();
        $deployment->division_type = $type->value;
        $deployment->has_been_deployed = false;
        $deployment->save();

        return $deployment;
    }
}
