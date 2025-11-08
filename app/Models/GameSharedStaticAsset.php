<?php

namespace App\Models;

use App\Domain\AssetType;
use App\Utils\GuardsForAssertions;
use App\Utils\MapsArrayToInstance;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

readonly class SharedStaticAssetMetadata {
    use MapsArrayToInstance;

    public function __construct(
        public string $file,
        public ?string $title,
        public ?string $description,
        public ?string $attribution,
    )
    {
        
    }
}

class FailedToReadMetaJson {}

class GameSharedStaticAsset extends Model
{
    use GuardsForAssertions;

    public const string FIELD_ASSET_TYPE = 'type';

    public function getType(): AssetType {
        return AssetType::from($this->type);
    }

    public function getSrc(): string {
        return $this->src;
    }

    public function updateWithMetadata(SharedStaticAssetMetadata $metadata): void {
        $this->title = $metadata->title;
        $this->description = $metadata->description;
        $this->attribution = $metadata->attribution;
        $this->save();
    }

    public function leaseTo(Nation $nation): void {
        $this->lessee_nation_id = $nation->getId();
        $this->save();
    }

    public static function whereAvailable(): Closure {
        return fn ($builder) => $builder
            ->whereNull('lessee_nation_id')
            ->where(function ($query) {
                 $query->whereNull('held_until')
                    ->orWhere('held_until', '<', CarbonImmutable::now('UTC'));
             });
    }

    private static function readMeta(string $pathToAssets): array|FailedToReadMetaJson {
        $pathToMeta = join(DIRECTORY_SEPARATOR, [$pathToAssets, 'meta.json']);
        
        if (!file_exists(public_path($pathToMeta))) {
            return new FailedToReadMetaJson;
        }

        $metaDatas = json_decode(file_get_contents(public_path($pathToMeta)), true);

        if (!is_array($metaDatas)) {
            return new FailedToReadMetaJson;
        }

        foreach ($metaDatas as &$metaData) {
            $metaData['file'] = join(DIRECTORY_SEPARATOR, [$pathToAssets, $metaData['file']]);
        }

        return $metaDatas;
    }

    public static function inventory(Game $game): void {
        $flagMetas = [];
        $metas = GameSharedStaticAsset::readMeta('res/bundled/flags');
        if (is_array($metas)) {
            $flagMetas = array_merge($flagMetas, $metas);
        }
        $metas = GameSharedStaticAsset::readMeta('res/local/flags');
        if (is_array($metas)) {
            $flagMetas = array_merge($flagMetas, $metas);
        }

        foreach($flagMetas as $meta) {
            $metadata = SharedStaticAssetMetadata::fromArray($meta);

            $assetOrNull = $game->staticAssetsOfType(AssetType::Flag)
                ->where('src', $metadata->file)
                ->first();

            if (is_null($assetOrNull)) {
                GameSharedStaticAsset::create(
                    game: $game,
                    metadata: $metadata,
                    type: AssetType::Flag,
                );
            }
            else {
                $asset = GameSharedStaticAsset::notNull($assetOrNull);

                $asset->updateWithMetadata($metadata);
            }
        }
    }

    public static function create(
        Game $game,
        SharedStaticAssetMetadata $metadata,
        AssetType $type,
    ): GameSharedStaticAsset {
        $asset = new GameSharedStaticAsset();
        $asset->game_id = $game->getId();
        $asset->src = $metadata->file;
        $asset->type = $type->value;
        $asset->title = $metadata->title;
        $asset->description = $metadata->description;
        $asset->attribution = $metadata->attribution;
        $asset->save();
        
        return $asset;
    }
}
