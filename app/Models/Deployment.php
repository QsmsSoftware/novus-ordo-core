<?php

namespace App\Models;

use App\Utils\GuardsForAssertions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

readonly class DeploymentInfo {
    public function __construct(
        public int $deployment_id,
        public int $nation_id,
        public int $territory_id,
    ) {}
}

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

    public function hasBeenDeployed() :int {
        return $this->has_been_deployed;
    }

    public function export() :DeploymentInfo {
        return new DeploymentInfo(
            deployment_id: $this->getId(),
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

    public static function Create(Nation $nation, Territory $territory) :Deployment {
        $deployment = new Deployment();
        $deployment->game_id = $nation->getGame()->getId();
        $deployment->nation_id = $nation->getId();
        $deployment->territory_id = $territory->getId();
        $deployment->turn_id = Turn::getCurrentForGame($nation->getGame())->getId();
        $deployment->has_been_deployed = false;
        $deployment->save();

        return $deployment;
    }
}
