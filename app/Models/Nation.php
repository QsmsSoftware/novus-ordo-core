<?php

namespace App\Models;

use App\Utils\GuardsForAssertions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

readonly class NationWithSameNameAlreadyExists {
    public function __construct(
        public string $otherNationName
    ) {}
}

readonly class TooExpensive {}

class Nation extends Model
{
    use GuardsForAssertions;

    public const string FIELD_USUAL_NAME = 'usual_name';

    public function game() :BelongsTo {
        return $this->belongsTo(Game::class);
    }

    public function getGame() :Game {
        return $this->game;
    }

    public function details() :HasMany {
        return $this->hasMany(NationDetail::class);
    }

    public function getDetail(?Turn $turnOrNull = null) :NationDetail {
        $turn = Turn::as($turnOrNull, fn () => Turn::getCurrentForGame($this->getGame()));
        return $this->details()->where('turn_id', $turn->getId())->first();
    }

    public function divisions() :HasMany {
        return $this->hasMany(Division::class);
    }

    public function deployments() :HasMany {
        return $this->hasMany(Deployment::class);
    }

    public function activeDeployments() :HasMany {
        return $this->hasMany(Deployment::class)
            ->where(Deployment::FIELD_HAS_BEEN_DEPLOYED, false);
    }

    public function deploymentByIds(int ...$ids) :HasMany {
        return $this->deployments()
            ->whereIn('id', $ids);
    }

    public function battlesWhereAttacker() :HasMany {
        return $this->hasMany(Battle::class, 'attacker_nation_id');
    }

    public function battlesWhereDefender() :HasMany {
        return $this->hasMany(Battle::class, 'defender_nation_id');
    }

    public function getId() :int {
        return $this->getKey();
    }
    public function getUsualName() :string {
        return $this->usual_name;
    }

    public function deploy(Territory $territory, int $numberOfDivisions) :array {
        if ($numberOfDivisions <= 0) {
            throw new LogicException("Parameter numberOfDivisions must be at least 1");
        }

        if ($numberOfDivisions > $this->getDetail()->getMaxRemainingDeployments()) {
            throw new LogicException("Parameter numberOfDivisions is greater than max remaining deployments.");
        }

        $deployments = [];

        for ($i = 0; $i < $numberOfDivisions; $i++) {
            $deployments[] = Deployment::Create($this, $territory);
        }

        return $deployments;
    }

    public function onNextTurn(Turn $currentTurn, Turn $nextTurn) :void {
        $currentDetail = $this->getDetail($currentTurn);
        $newDetail = $currentDetail->replicateForTurn($nextTurn);
        $newDetail->onNextTurn($currentDetail);

        $currentDetail->deployments()->get()->each(fn (Deployment $d) => $d->execute());
    }

    public function equals(?Nation $otherNationOrNull) :bool {
        return ($otherNation = $otherNationOrNull??false)
            && $this->getId() == $otherNation->getId();
    }

    public static function getCurrent() :Nation {
        return Nation::getCurrentOrNull();
    }

    public static function getCurrentOrNull() :Nation|null {
        return Nation::getForUserOrNull(Game::getCurrent(), User::getCurrent());
    }

    public static function getForUserOrNull(Game $game, User $user) :Nation|null {
        return Nation::where('game_id', $game->getId())
            ->where('user_id', $user->getId())
            ->first();
    }

    public static function create(Game $game, User $user, string $usualName) :Nation|NotEnoughFreeTerritories|NationWithSameNameAlreadyExists {
        if ($game->hasNationWithUsualName($usualName)) {
            return new NationWithSameNameAlreadyExists($usualName);
        }

        $existingUserOrNull = Nation::getForUserOrNull($game, $user);

        if ($existingUserOrNull !== null) {
            throw new LogicException("User ID '{$user->getId()}' already has a nation in game ID '{$game->getId()}'");
        }

        $numberOfTerritoriesValidation = $game->hasEnoughTerritoriesForNewNation();

        if ($numberOfTerritoriesValidation instanceof NotEnoughFreeTerritories) {
            return $numberOfTerritoriesValidation;
        }

        $nation = new Nation();
        $nation->game_id = $game->getId();
        $nation->user_id = $user->getId();
        $nation->usual_name = $usualName;
        $nation->save();

        NationDetail::create($nation);

        $game->freeSuitableTerritoriesInTurn()->take(Game::NumberOfStartingTerritories)->get()->each(fn (Territory $territory) => $territory->getDetail()->assignOwner($nation));

        return $nation;
    }
}
