<?php

namespace App\Models;

use App\Domain\NationSetupStatus;
use App\Services\StaticJavascriptResource;
use App\Utils\GuardsForAssertions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Cache;
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
    private const CRITICAL_SECTION_HOME_TERRITORIES_SELECT_CACHE_NAME = 'critical_section:home_territories_select';

    protected $table = 'nations';

    public function game(): BelongsTo {
        return $this->BelongsTo(Game::class);
    }

    public function getGame(): Game {
        return $this->game;
    }

    public function getId(): int {
        return $this->id;
    }

    public function getSetupStatus(): NationSetupStatus {
        return NationSetupStatus::from($this->nation_setup_status);
    }

    public function rename(string $usualName) {
        if ($this->anotherNationHasTheSameName($usualName)) {
            throw new LogicException("Another nation is named '$usualName'");
        }
        $this->usual_name = $usualName;
        $this->save();
    }

    private function anotherNationHasTheSameName(string $usualName): bool {
        return NewNation::withoutGlobalScopes()
            ->where('game_id', $this->getGame()->getId())
            ->whereRaw('LOWER(' . Nation::FIELD_USUAL_NAME . ') = ?', strtolower($usualName))
            ->whereNot('id', $this->getId())
            ->exists();
    }

    public function finishSetup(int ...$homeTerritoryIds): Nation {
        if (count($homeTerritoryIds) != Game::NUMBER_OF_STARTING_TERRITORIES) {
            throw new LogicException("Parameter homeTerritoryIds: expecting " . Game::NUMBER_OF_STARTING_TERRITORIES . " IDs, " . count($homeTerritoryIds) . " specified");
        }

        $nation = Cache::lock(NewNation::CRITICAL_SECTION_HOME_TERRITORIES_SELECT_CACHE_NAME, 10)->block(2, function () use ($homeTerritoryIds) {
            $nation = Nation::notNull(Nation::withoutGlobalScopes()->find($this->getId()));
            
            $homeTerritories = $nation->getGame()->freeSuitableTerritoriesInTurn()->whereIn('id', $homeTerritoryIds)->get();
            
            $homeTerritories->each(function (Territory $territory) {
                if (!$territory->isSuitableAsHome()) {
                    throw new LogicException("Territory ID {$territory->getId()} is not suitable as home territory");
                }
            });

            $homeTerritories->each(fn (Territory $territory) => $territory->getDetail()->assignOwner($nation));

            $nation->nation_setup_status = NationSetupStatus::FinishedSetup;
            NationDetail::create($nation);
            $nation->save();

            StaticJavascriptResource::expireAllForGame($nation->getGame());

            return $nation;
        });

        assert($nation instanceof Nation);

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

    public static function getForUserOrNull(Game $game, User $user): ?NewNation {
        return NewNation::where('user_id', $user->getId())
            ->where('game_id', $game->getId())
            ->first();
    }

    public static function createRuleNoNationWithSameNameInGame(Game $game): Unique {
        return Rule::unique(NewNation::class, 'usual_name')
            ->where('game_id', $game->getId());
    }

    public static function createRuleNoNationWithSameNameInGameUnlessItsOwner(Game $game, User $user): Unique {
        return Rule::unique(NewNation::class, 'usual_name')
            ->where('game_id', $game->getId())
            ->whereNot('user_id', $user->getId());
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
        if (NewNation::userAlreadyHasANationInGame($game, $user)) {
            throw new LogicException("User ID '{$user->getId()}' already has a nation in game ID '{$game->getId()}'");
        }

        if (NewNation::nationWithSameNameAlreadyExistsInGame($game, $usualName)) {
            return new NationWithSameNameAlreadyExists($usualName);
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