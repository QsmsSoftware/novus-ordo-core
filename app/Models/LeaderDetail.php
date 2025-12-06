<?php

namespace App\Models;

use App\ReadModels\LeaderTurnPublicInfo;
use App\Utils\ImageSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class LeaderDetail extends Model
{
    public static function getAll(Turn $turn): Collection {
        return LeaderDetail::where('game_id', $turn->getGameId())
            ->where('turn_id', $turn->getId())
            ->get();
    }

    public function export(): LeaderTurnPublicInfo {
        return new LeaderTurnPublicInfo(
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
