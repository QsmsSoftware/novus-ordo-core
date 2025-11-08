<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use LogicException;

readonly class ParticipantBattleLog {
    public function __construct(
        public int $battle_id,
        public int $turn_number,
        public int $territory_id,
        public int $attacker_nation_id,
        public ?int $defender_nation_id,
        public ?int $winner_nation_id,
        public string $text,
    )
    {
        
    }
}

class Battle extends Model
{
    private const float ATTACK_POWER_PER_DIVISION = 0.3;
    private const float DEFENSE_POWER_PER_DIVISION = 0.5;
    private const int MIN_NEUTRAL_DIVISIONS = 1;
    private const int MAX_NEUTRAL_DIVISIONS = 3;

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

    public static function resolveBattle(Territory $territory, Collection $attackingDivisions): Battle {
        $log = "";

        $numberOfAttackers = $attackingDivisions->count();
        if ($numberOfAttackers < 1) {
            throw new LogicException("There must be at least 1 attacking division.");
        }
        $firstDiv = Division::notNull($attackingDivisions->first());
        $attacker = $firstDiv->getNation();
        $attackingDivisions->each(function (Division $d) use ($attacker) {
            if (!$d->getNation()->equals($attacker)) {
                throw new LogicException("All attacking divisions must have the same owner.");
            }
        });
        $attackPower = Battle::ATTACK_POWER_PER_DIVISION * $numberOfAttackers;

        $defenderOrNull = $territory->getDetail()->getOwnerOrNull();
        $defenderIsNeutral = is_null($defenderOrNull);
        $defenderDescription = $defenderIsNeutral ? "neutral territory" : "{$defenderOrNull->getDetail()->getUsualName()} on territory";
        
        $log .= "$numberOfAttackers divisions of {$attacker->getDetail()->getUsualName()} attacks {$defenderDescription} {$territory->getName()} (ID {$territory->getId()}).\n";
        
        if ($defenderIsNeutral) {
            $numberOfDefenders = random_int(Battle::MIN_NEUTRAL_DIVISIONS, Battle::MAX_NEUTRAL_DIVISIONS);
            $log .= 'Rolled between ' . Battle::MIN_NEUTRAL_DIVISIONS . ' and ' . Battle::MAX_NEUTRAL_DIVISIONS . " for number of neutral divisions: $numberOfDefenders divisions will be defending.\n";
        }
        else {
            $numberOfDefenders = $territory->getDetail()->getOwnerDivisions()->count();
            $log .= "The defender has $numberOfDefenders divisions.\n";
        }
        $defensePower = Battle::DEFENSE_POWER_PER_DIVISION * $numberOfDefenders;

        $attackerRoll = random_int(1, 100) / 100;
        $remainingAttackPower = $attackPower - floor($attackPower);
        $attackerBaseKills = floor($attackPower);
        $attackerKills = min($attackerBaseKills + ($attackerRoll <= $remainingAttackPower ? 1 : 0), $numberOfDefenders);

        $log .= "Attacker's power ($numberOfAttackers divisions x " . Battle::ATTACK_POWER_PER_DIVISION . "): $attackPower\n";
        $log .= "Attacker's base kills: $attackerBaseKills\n";
        $log .= "Attacker's remaining power: $remainingAttackPower\n";
        if ($remainingAttackPower >= 0.01) {
            $log .= "Attacker's d100 roll: $attackerRoll\n";
            $log .= "Attacker " . ($attackerRoll <= $remainingAttackPower ? " rolled below or equal their remaining power and scores an extra kill." : "rolled above their remaining power and doesn't score an extra kill.") . "\n";
        }
        else {
            $log .= "No remaining power for attacker, no chance for extra kill.\n";
        }
        $log .= "Attacker total kills: $attackerKills / $numberOfDefenders divisions\n";
        
        $defenderRoll = random_int(1, 100) / 100;
        $remainingDefensePower = $defensePower - floor($defensePower);
        $defenderBaseKills = floor($defensePower);
        $defenderKills = min($defenderBaseKills + ($defenderRoll <= $remainingDefensePower ? 1 : 0), $numberOfAttackers);
        $log .= "Defender's power ($numberOfDefenders divisions x " . Battle::DEFENSE_POWER_PER_DIVISION . "): $defensePower\n";
        $log .= "Defender's base kills: $defenderBaseKills\n";
        $log .= "Defender's remaining power: $remainingDefensePower\n";
        if ($remainingDefensePower >= 0.01) {
            $log .= "Defender's d100 roll: $defenderRoll\n";
            $log .= "Defender " . ($defenderRoll <= $remainingDefensePower ? " rolled below or equal their remaining power and scores an extra kill." : "rolled above their remaining power and doesn't score an extra kill.") . "\n";
        }
        else {
            $log .= "No remaining power for defender, no chance for extra kill.\n";
        }
        $log .= "Defender total kills: $defenderKills / $numberOfAttackers divisions\n";

        $attackingDivisions->take($defenderKills)->each(fn (Division $defeatedDivision) => $defeatedDivision->getDetail()->disband());

        if (!$defenderIsNeutral) {
            $territory->getDetail()->getOwnerDivisions()->take($attackerKills)->each(fn (Division $defeatedDivision) => $defeatedDivision->getDetail()->disband());
        }

        $someAttackersRemain = $numberOfAttackers - $defenderKills > 0;
        $someDefendersRemain = $numberOfDefenders - $attackerKills > 0;

        if ($someAttackersRemain && !$someDefendersRemain) {
            $winnerOrNull = $attacker;
            $territory->getDetail()->assignOwner($attacker);
            $log .= "Attacker won. Territory conquered.";
            $attackingDivisions->each(function (Division $division) use ($territory) {
                if ($division->getDetail()->isActive()) {
                    $division->getDetail()->moveTo($territory);
                }
            });
        }
        else {
            $winnerOrNull = $defenderOrNull;
            $log .= "Defender repelled the attack.";
        }

        return Battle::create($territory, $attacker, $defenderOrNull, $winnerOrNull, $log);
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