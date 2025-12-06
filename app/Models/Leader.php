<?php

namespace App\Models;

use App\Utils\ImageSource;
use Illuminate\Database\Eloquent\Model;

class Leader extends Model
{
    public function getId(): int {
        return $this->id;
    }

    public function getGameId(): int {
        return $this->game_id;
    }

    public function getNationId(): int {
        return $this->nation_id;
    }

    public static function create(NationDetail $nationDetail, string $name, ?string $titleOrNull = null, ?ImageSource $pictureSrcOrNull = null): Leader {
        $leader = new Leader();
        $leader->game_id = $nationDetail->getGameId();
        $leader->nation_id = $nationDetail->getNationId();
        $leader->save();

        $title = $titleOrNull ?? "Emperor";

        LeaderDetail::create($leader, $nationDetail->getTurn(), $name, $title, $pictureSrcOrNull);

        return $leader;
    }
}
