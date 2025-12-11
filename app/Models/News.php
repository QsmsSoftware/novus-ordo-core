<?php

namespace App\Models;

use App\ReadModels\NewsInfo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class News extends Model
{
    public function export(): NewsInfo {
        return new NewsInfo($this->content);
    }

    public static function getNationUsualNameTag(NationDetail $nationDetail): string {
        return "##nation#{$nationDetail->getNationId()}#usual_name##";
    }

    public static function getNationFormalNameTag(NationDetail $nationDetail): string {
        return "##nation#{$nationDetail->getNationId()}#formal_name##";
    }

    public static function getLeaderNameTag(LeaderDetail $leaderDetail): string {
        return "##leader#{$leaderDetail->getLeaderId()}#name##";
    }

    public static function getLeaderTitleTag(LeaderDetail $leaderDetail): string {
        return "##leader#{$leaderDetail->getLeaderId()}#title##";
    }

    public static function getTerritoryNameTag(Territory $territory): string {
        return "##territory#{$territory->getId()}#name##";
    }

    public static function getAllForTurn(Turn $turn): Collection {
        return News::where('game_id', $turn->getGameId())
            ->where('turn_id', $turn->getId())
            ->get();
    }

    public static function create(Turn $turn, string $content): News {
        $news = new News();
        $news->game_id = $turn->getGame()->getId();
        $news->turn_id = $turn->getId();
        $news->content = $content;
        $news->save();

        return $news;
    }
}
