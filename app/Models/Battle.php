<?php

namespace App\Models;

use App\Domain\DivisionType;
use App\Domain\ResourceType;
use App\ReadModels\ParticipantBattleLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use LogicException;
use PhpOption\Option;

class BattleFormation {
    private bool $isActive = true;

    public function isActive(): bool {
        return $this->isActive;
    }

    public function kill(): void {
        $this->isActive = false;
    }

    public function __construct(
        public readonly string $description,
        public readonly int $power,
        public readonly int $value,
        public readonly bool $canTakeTerritory,
        public readonly ?Division $linkedDivision = null,
    )
    {
        
    }

    public static function fromDivision(Division $division, NationDetail $ownerDetail, int $power): BattleFormation {
        $meta = DivisionType::getMeta($division->getDivisionType());

        return new BattleFormation($meta->description . " ({$ownerDetail->getUsualName()})", $power, $meta->deploymentCosts[ResourceType::Capital->value],
            canTakeTerritory: $meta->canTakeTerritory,
            linkedDivision: $division
        );
    }

    public static function newPartisans(NationDetail $ownerDetail, int $power) {
        $description = "Partisans ({$ownerDetail->getUsualName()})";

        return new BattleFormation($description, $power, 0, canTakeTerritory: true);
    }

    public static function newMilitia(?NationDetail $ownerDetail, int $power) {
        $faction = is_null($ownerDetail) ? "neutral" : $ownerDetail->getUsualName();
        $description = "Militia ($faction)";

        return new BattleFormation($description, $power, 0, canTakeTerritory: true);
    }
}

readonly class BattleLosses {
    public function __construct(
        public int $numberOfFormations,
        public int $totalLosses,
        public bool $anySurvivors,
        public Collection $destroyedFormations,
        public Collection $destroyedDivisions,
    )
    {
        
    }
}

readonly class BattleKills {
    public function __construct(
        public int $totalPower,
        public int $baseKills,
        public int $remainingPower,
        public int $roll,
        public bool $extraKill,
        public int $totalKills,
    )
    {
        
    }
}

class Battle extends Model
{
    private const float MIN_SUPPORT_FOR_MILITIA = 0.50;
    private const int MIN_MILITIA_BASE_FORMATIONS = 1;
    private const int MAX_MILITIA_BASE_FORMATIONS = 3;
    private const int SUPPORTERS_PER_EXTRA_FORMATION = 2_000_000;
    private const int PARTISANS_POWER = 30;
    private const int MILITIA_POWER = 30;

    public function game(): BelongsTo {
        return $this->belongsTo(Game::class);
    }

    public function getGame(): Game {
        return $this->game;
    }

    public function turn(): BelongsTo {
        return $this->belongsTo(Turn::class);
    }

    public function getTurn(): Turn {
        return $this->turn;
    }

    public function territory(): BelongsTo {
        return $this->belongsTo(Territory::class);
    }

    public function getTerritory(): Territory {
        return $this->territory;
    }

    public function getAttacker(): Nation {
        return $this->getGame()->getNationWithIdOrNull($this->attacker_nation_id);
    }

    public function getDefenderOrNull(): ?Nation {
        return is_null($this->defender_nation_id) ? null : Nation::notNull($this->getGame()->getNationWithIdOrNull($this->defender_nation_id));
    }

    public function getWinnerOrNull(): ?Nation {
        return is_null($this->winner_nation_id) ? null : Nation::notNull($this->getGame()->getNationWithIdOrNull($this->winner_nation_id));
    }

    public function getId(): int {
        return $this->id;
    }

    public function getLog(): string {
        return $this->log;
    }

    private static function calculateTotalPower(Collection $formations): int {
        return $formations->sum(fn (BattleFormation $f) => $f->power);
    }

    private static function processSide(Collection $formations): BattleKills {
        $totalPower = Battle::calculateTotalPower($formations);

        $baseKills = floor($totalPower / 100);
        $remainingPower = $totalPower - $baseKills * 100;
        $roll = random_int(1, 100);
        $extraKill = $roll <= $remainingPower;
        $totalKills = $extraKill ? $baseKills + 1 : $baseKills;

        return new BattleKills(
            totalPower: $totalPower,
            baseKills: $baseKills,
            remainingPower: $remainingPower,
            roll: $roll,
            extraKill: $extraKill,
            totalKills: $totalKills
        );
    }

    private static function listFormations(Collection $formations): string {
        $formationsByType = $formations->groupBy(fn (BattleFormation $f) => $f->description)->sortBy(fn (Collection $formations) => $formations->first()->value);
        $described = [];
        $longestDescLength = $formationsByType->max(fn (Collection $formations) => strlen($formations->first()->description));

        foreach ($formationsByType->keys() as $desc) {
            $count = $formationsByType->get($desc)->count();
            $power = $formationsByType->get($desc)->first()->power;
            $cumulatedPower = $power * $count;
            
            $described[] =  "{$count}x " . str_pad($desc, $longestDescLength) . " [power: $power, cumulated power: $cumulatedPower]";
        }

        return join("\n", $described);
    }

    private static function describeFormations(string $side, Collection $formations): string {
        $totalPower = Battle::calculateTotalPower($formations);
        return "$side [{$formations->count()} formations, $totalPower total power]:\n" . Battle::listFormations($formations);
    }

    private static function describeKills(string $side, BattleKills $kills): string {
        $log = "";

        $log .= "{$side}'s total power                      : {$kills->totalPower}\n";
        $log .= "{$side}'s base kills (1 kill per 100 power): {$kills->baseKills}\n";
        $log .= "{$side}'s remaining power                  : {$kills->remainingPower}\n";
        if ($kills->remainingPower > 0) {
            $log .= "{$side} rolls                              : {$kills->roll}";
            if ($kills->extraKill) {
                $log .= " (rolled below or equal to {$kills->remainingPower}, extra kill!)\n";
            }
            else {
                $log .= " (rolled above {$kills->remainingPower}, no extra kill!)\n";
            }
        }
        else {
            $log .= "{$side} has no remaining power, no roll for extra kill.\n";
        }
        $log .= "{$side}'s total kills                      : {$kills->totalKills}\n";

        return $log;
    }

    public static function applyLosses(BattleKills $kills, Collection $formations): BattleLosses {
        $totalLosses = min($kills->totalKills, $formations->count());

        $formationOrderedByValue = $formations->sortBy(fn (BattleFormation $f) => $f->value);

        $destroyedFormations = $formationOrderedByValue->take($totalLosses);

        $destroyedDivisions = $destroyedFormations->filter(fn (BattleFormation $f) => !is_null($f->linkedDivision))->map(fn (BattleFormation $f) => $f->linkedDivision);

        return new BattleLosses(
            numberOfFormations: $formations->count(),
            totalLosses: $totalLosses,
            anySurvivors: $totalLosses < $formations->count(),
            destroyedFormations: $destroyedFormations,
            destroyedDivisions: $destroyedDivisions,
        );
    }

    public static function describeLosses(string $side, BattleLosses $losses): string {
        $numberOfDestroyedDivisions = count($losses->destroyedDivisions);
        return "$side lost {$losses->totalLosses} formations, among them $numberOfDestroyedDivisions divisions:\n" . Battle::listFormations($losses->destroyedFormations);
    }

    public static function resolveBattle(Territory $territory, Turn $currentTurn, Turn $nextTurn, Collection $attackingDivisions): Battle {
        $teritoryDetail = $territory->getDetail($currentTurn);
        $log = "";

        $numberOfAttackers = $attackingDivisions->count();
        if ($numberOfAttackers < 1) {
            throw new LogicException("There must be at least 1 attacking division.");
        }
        $firstDiv = Division::notNull($attackingDivisions->first());
        $attacker = $firstDiv->getNation();
        $attackerDetail = $attacker->getDetail($currentTurn);
        $attackingDivisions->each(function (Division $d) use ($attacker) {
            if (!$d->getNation()->equals($attacker)) {
                throw new LogicException("All attacking divisions must have the same owner.");
            }
        });

        $divisionInfoByType = DivisionType::getMetas();

        $attackingFormations = $attackingDivisions
            ->filter(fn (Division $d) => $d->getDetail($nextTurn)->isActive())
            ->map(fn (Division $d) => BattleFormation::fromDivision($d, $attackerDetail, $divisionInfoByType->get($d->getDivisionType()->value)->attackPower));
        $attackerLoyaltyRatio = Option::fromValue(NationTerritoryLoyalty::getLoyaltyOrNull($attacker, $territory, $currentTurn))
                ->map(fn (NationTerritoryLoyalty $l) => $l->getLoyaltyRatio())
                ->getOrElse(0.00);
        $numberOfPartisans = floor($attackerLoyaltyRatio * $teritoryDetail->getPopulationSize() / Battle::SUPPORTERS_PER_EXTRA_FORMATION);
        
        for ($i = 0; $i < $numberOfPartisans; $i++) {
            $attackingFormations->push(BattleFormation::newPartisans($attacker->getDetail($currentTurn), Battle::PARTISANS_POWER));
        }

        $defenderOrNull = $territory->getDetail()->getOwnerOrNull();
        $defenderIsNeutral = is_null($defenderOrNull);
        $defenderDescription = $defenderIsNeutral ? "neutral territory" : "{$defenderOrNull->getDetail()->getUsualName()} on";

        $log .= "{$attacker->getDetail()->getUsualName()} attacks {$defenderDescription} {$territory->getName()}.";
        $log .= "\n\n";

        if ($attackingFormations->count() < 1) {
            return Battle::create($territory, $attacker, $defenderOrNull, $defenderOrNull, "No attacking formation is still operational. Attack aborted.");
        }

        if ($defenderIsNeutral) {
            $numberOfMilitias = random_int(Battle::MIN_MILITIA_BASE_FORMATIONS, Battle::MAX_MILITIA_BASE_FORMATIONS);
            $defendingFormations = collect();
        }
        else {
            $defenderDetail = Nation::notNull($defenderOrNull)->getDetail($currentTurn);
            $defenderLoyaltyRatio = Option::fromValue(NationTerritoryLoyalty::getLoyaltyOrNull($defenderOrNull, $territory, $currentTurn))
                ->map(fn (NationTerritoryLoyalty $l) => $l->getLoyaltyRatio())
                ->getOrElse(0.00);
            if ($defenderLoyaltyRatio >= Battle::MIN_SUPPORT_FOR_MILITIA) {
                $log .= "The territory's current owner has enough support for the population to take up arms and form militia formations.\n";
                $numberOfMilitias = Battle::MIN_MILITIA_BASE_FORMATIONS
                    + random_int(0, round($defenderLoyaltyRatio * (Battle::MAX_MILITIA_BASE_FORMATIONS - Battle::MIN_MILITIA_BASE_FORMATIONS)))
                    + floor($defenderLoyaltyRatio * $teritoryDetail->getPopulationSize() / Battle::SUPPORTERS_PER_EXTRA_FORMATION);
            }
            else {
                $log .= "The territory's current owner doesn't have enough support for the population to take up arms and form militia formations.\n";
                $numberOfMilitias = 0;
            }

            $log .= "\n";

            $defendingFormations = $territory->getDetail($nextTurn)
                ->getOwnerDivisions()
                ->map(fn (Division $d) => BattleFormation::fromDivision($d, $defenderDetail, $divisionInfoByType->get($d->getDivisionType()->value)->defensePower));
        }
        
        for ($i = 0; $i < $numberOfMilitias; $i++) {
            $defendingFormations->push(BattleFormation::newMilitia($defenderOrNull?->getDetail($currentTurn), Battle::MILITIA_POWER));
        }

        $log .= Battle::describeFormations("Attacking forces ({$attackerDetail->getUsualName()})", $attackingFormations);
        $log .= "\n\n";

        if ($defendingFormations->count() < 1) {
            if ($attackingFormations->some(fn (BattleFormation $f) => $f->canTakeTerritory)) {
                Battle::finalizeAttackerVictory($territory, $attacker, $attackingDivisions);
                return Battle::create($territory, $attacker, $defenderOrNull, $attacker, $log . "No active formation was able to defend the territory. Territory conquered by attacker.");
            }
            else {
                return Battle::create($territory, $attacker, $defenderOrNull, $defenderOrNull, $log . "No active formation was able to defend the territory but no active attacking formation is able to take the territory. Territory couldn't be conquered by attacker.");
            }
        }

        $log .= Battle::describeFormations('Defending forces (' . ($defenderIsNeutral ? 'neutral' : NationDetail::notNull($defenderDetail)->getUsualName()) . ')', $defendingFormations);
        $log .= "\n\n";
        
        $attackerKills = Battle::processSide($attackingFormations);
        $log .= Battle::describeKills('Attacker', $attackerKills);
        $log .= "\n\n";

        $defenderKills = Battle::processSide($defendingFormations);
        $log .= Battle::describeKills('Defender', $defenderKills);
        $log .= "\n\n";

        $log .= "End of battle!";
        $log .= "\n\n";

        $attackerLosses = Battle::applyLosses($defenderKills, $attackingFormations);
        $log .= Battle::describeLosses('Attacker', $attackerLosses);
        $log .= "\n\n";

        $defenderLosses = Battle::applyLosses($attackerKills, $defendingFormations);
        $log .= Battle::describeLosses('Defender', $defenderLosses);
        $log .= "\n\n";

        $attackerLosses->destroyedDivisions->each(fn (Division $defeatedDivision) => $defeatedDivision->getDetail()->disband());

        $defenderLosses->destroyedDivisions->each(fn (Division $defeatedDivision) => $defeatedDivision->getDetail()->disband());

        $someAttackersRemain = $attackerLosses->anySurvivors;
        $someDefendersRemain = $defenderLosses->anySurvivors;

        if ($someAttackersRemain && !$someDefendersRemain) {
            if ($attackingFormations->some(fn (BattleFormation $f) => $f->canTakeTerritory)) {
                $winnerOrNull = $attacker;
                Battle::finalizeAttackerVictory($territory, $attacker, $attackingDivisions);
                $log .= "Attacker won. Territory conquered.";
            }
            else {
                $winnerOrNull = $defenderOrNull;
                $log .= "Attacker won the battle but no remaining formation could take the territory. Territory could not be conquered by attacker.";
            }
        }
        else {
            $winnerOrNull = $defenderOrNull;
            $log .= "Defender repelled the attack.";
        }

        return Battle::create($territory, $attacker, $defenderOrNull, $winnerOrNull, $log);
    }

    private static function finalizeAttackerVictory(Territory $territory, Nation $attacker, Collection $attackingDivisions): void {
        $territory->getDetail()->conquer($attacker);

        $attackingDivisions->each(function (Division $division) use ($territory) {
                if ($division->getDetail()->isActive()) {
                    $division->getDetail()->moveTo($territory);
                }
            });
    }

    public function exportForParticipant(): ParticipantBattleLog {
        return new ParticipantBattleLog(
            battle_id: $this->getId(),
            turn_number: $this->getTurn()->getId(),
            territory_id: $this->getTerritory()->getId(),
            attacker_nation_id: $this->getAttacker()->getId(),
            defender_nation_id: $this->getDefenderOrNull()?->getId(),
            winner_nation_id: $this->getWinnerOrNull()?->getId(),
            text: $this->getLog()
        );
    }

    private static function create(Territory $territory, Nation $attacker, ?Nation $defenderOrNull, ?Nation $winnerOrNull, string $log): Battle {
        $game = $territory->getGame();
        $turn = Turn::getCurrentForGame($game);

        $battle = new Battle();
        $battle->game_id = $game->getId();
        $battle->turn_id = $turn->getId();
        $battle->territory_id = $territory->getId();
        $battle->attacker_nation_id = $attacker->getId();
        $battle->defender_nation_id = $defenderOrNull?->getId();
        $battle->winner_nation_id = $winnerOrNull?->getId();
        $battle->log = $log;
        $battle->save();

        return $battle;
    }
}