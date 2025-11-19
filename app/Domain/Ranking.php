<?php

namespace App\Domain;

use App\Facades\Metacache;
use App\Models\NationDetail;
use Closure;
use Illuminate\Support\Collection;
use InvalidArgumentException;

readonly class Ranking {
    private Closure $valuePostProcessing;

    private function __construct(
        public string $title,
        private Closure $valueGetter,
        public int $sortOrder,
        public StatUnit $unit,
        ?Closure $valuePostProcessing = null,
    )
    {
        if ($sortOrder != SORT_ASC && $sortOrder != SORT_DESC) {
            throw new InvalidArgumentException("sortOrder: must be either SORT_ASC or SORT_DESC");
        }

        $this->valuePostProcessing = is_null($valuePostProcessing) ? fn ($v) => $v : $valuePostProcessing;
    }

    public static function rankNations(NationDetail ...$nationDetails): array {
        $nationDetails = collect($nationDetails);
        $rankingMetas = Ranking::getRankings();
        $rankings = [];

        foreach ($rankingMetas as $rankingMeta) {
            assert($rankingMeta instanceof Ranking);

            $rankings[] = $nationDetails->mapWithKeys(fn (NationDetail $d) => [$d->getNationId() => ($rankingMeta->valueGetter)($d)])
                ->sortBy(fn ($v) => $v, descending: $rankingMeta->sortOrder == SORT_DESC)
                ->mapWithKeys(fn ($v, int $nationId) => [$nationId => ($rankingMeta->valuePostProcessing)($v)]);
        }

        return $rankings;
    }

    private static function approximate(int $value): int {
        if ($value < 5) {
            return 5;
        }

        if ($value < 50) {
            return round($value / 10) * 10;
        }

        if ($value < 100) {
            return round($value / 20) * 20;
        }

        if ($value < 200) {
            return round ($value / 40) * 40;
        }

        if ($value < 400) {
            return round ($value / 80) * 80;
        }

        return round($value / 100) * 100;
    }

    public static function getRankings(): array {
        return [
            new Ranking('Area', fn (NationDetail $d) => Metacache::remember($d->getUsableLandKm2(...)), SORT_DESC, StatUnit::Km2),
            new Ranking('Number of territories', fn (NationDetail $d) => $d->territories()->count(), SORT_DESC, StatUnit::WholeNumber),
            new Ranking('Population', fn (NationDetail $d) => $d->getPopulationSize(), SORT_DESC, StatUnit::WholeNumber),
            new Ranking('Army size (number of divisions)', fn (NationDetail $d) => $d->activeDivisions()->count(), SORT_DESC, StatUnit::ApproximateNumber, valuePostProcessing: fn (int $count) => Ranking::approximate($count)),
        ];
    }
}