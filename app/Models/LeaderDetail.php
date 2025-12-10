<?php

namespace App\Models;

use App\ModelTraits\ReplicatesForTurns;
use App\ReadModels\LeaderTurnPublicInfo;
use App\Utils\ImageSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class LeaderDetail extends Model
{
    use ReplicatesForTurns;

    public function getLeaderId(): int {
        return $this->leader_id;
    }

    public function onNextTurn(LeaderDetail $current): void {
        $this->save();
    }

    public static function getAll(Turn $turn): Collection {
        return LeaderDetail::where('game_id', $turn->getGameId())
            ->where('turn_id', $turn->getId())
            ->get();
    }

    public static function getForNation(NationDetail $nationDetail): LeaderDetail {
        return LeaderDetail::where('game_id', $nationDetail->getGameId())
            ->where('nation_id', $nationDetail->getNationId())
            ->where('turn_id', $nationDetail->getTurnId())
            ->first();
    }

    public function export(): LeaderTurnPublicInfo {
        return new LeaderTurnPublicInfo(
            leader_id: $this->leader_id,
            nation_id: $this->nation_id,
            name: $this->name,
            title: $this->title,
            picture_src: $this->picture_src
        );
    }

    public static function create(Leader $leader, Turn $turn, string $name, string $title, ?ImageSource $pictureSrcOrNull = null): LeaderDetail {
        $detail = new LeaderDetail();
        $detail->game_id = $leader->getGameId();
        $detail->nation_id = $leader->getNationId();
        $detail->leader_id = $leader->getId();
        $detail->turn_id = $turn->getId();
        $detail->name = $name;
        $detail->title = $title;
        $detail->picture_src = $pictureSrcOrNull?->src;
        $detail->save();

        return $detail;
    }
}
