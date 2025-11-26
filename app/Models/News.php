<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class News extends Model
{
    public static function create(Turn $turn, string $content): News {
        $news = new News();
        $news->game_id = $turn->getGame()->getId();
        $news->turn_id = $turn->getId();
        $news->content = $content;
        $news->save();

        return $news;
    }
}
