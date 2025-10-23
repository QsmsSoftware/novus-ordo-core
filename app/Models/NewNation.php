<?php

namespace App\Models;

use App\Domain\NationSetupStatus;
use App\Utils\GuardsForAssertions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use LogicException;

readonly class NationWithSameNameAlreadyExists {
    public function __construct(
        public string $otherNationName
    ) {}
}

class NewNation extends Model
{
    use GuardsForAssertions;

    protected $table = 'nations';

    public function getId(): int {
        return $this->id;
    }

    public function finishSetup(Collection $homeTerritories): Nation {
        if ($homeTerritories->count() != Game::NUMBER_OF_STARTING_TERRITORIES) {
            throw new LogicException("Parameter homeTerritories: expecting " . Game::NUMBER_OF_STARTING_TERRITORIES . " territories, " . $homeTerritories->count() . " specified");
        }

        $homeTerritories->each(function (Territory $territory) {
            if (!$territory->isSuitableAsHome()) {
                throw new LogicException("Territory ID {$territory->getId()} is not suitable as home territory");
            }
        });

        $nation = Nation::notNull(Nation::withoutGlobalScopes()->find($this->getId()));

        $homeTerritories->each(fn (Territory $territory) => $territory->getDetail()->assignOwner($nation));

        $nation->nation_setup_status = NationSetupStatus::FinishedSetup;
        NationDetail::create($nation);
        $nation->save();

        return $nation;
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('ancient', function (Builder $builder) {
            $builder->whereNot('nation_setup_status', NationSetupStatus::FinishedSetup->value);
        });
    }

    public static function createRuleNoNationWithSameNameInGame(Game $game): Unique {
        return Rule::unique(NewNation::class, 'usual_name')
            ->where('game_id', $game->getId());
    }

    private static function nationWithSameNameAlreadyExistsInGame(Game $game, string $usualName): bool {
        return NewNation::withoutGlobalScopes()
            ->where('game_id', $game->getId())
            ->whereRaw('LOWER(' . Nation::FIELD_USUAL_NAME . ') = ?', strtolower($usualName))
            ->exists();
    }

    private static function userAlreadyHasANationInGame(Game $game, User $user): bool {
        return NewNation::withoutGlobalScopes()
            ->where('game_id', $game->getId())
            ->where('user_id', $user->getId())
            ->exists();
    }

    public static function create(Game $game, User $user, string $usualName): NewNation {
        return NewNation::tryCreate($game, $user, $usualName);
    }

    public static function tryCreate(Game $game, User $user, string $usualName): NewNation|NationWithSameNameAlreadyExists {
        if (NewNation::nationWithSameNameAlreadyExistsInGame($game, $usualName)) {
            return new NationWithSameNameAlreadyExists($usualName);
        }

        if (NewNation::userAlreadyHasANationInGame($game, $user)) {
            throw new LogicException("User ID '{$user->getId()}' already has a nation in game ID '{$game->getId()}'");
        }

        $nation = new NewNation();
        $nation->game_id = $game->getId();
        $nation->user_id = $user->getId();
        $nation->nation_setup_status = NationSetupStatus::HomeTerritoriesSelection->value;
        $nation->usual_name = $usualName;
        $nation->save();

        return $nation;
    }
}