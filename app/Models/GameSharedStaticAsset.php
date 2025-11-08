<?php

namespace App\Models;

use App\Domain\AssetType;
use App\Utils\GuardsForAssertions;
use App\Utils\MapsArrayToInstance;
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

    private static function readMeta(string $pathToMeta): array|false {
        if (!file_exists($pathToMeta)) {
            return false;
        }

        $metaData = json_decode(file_get_contents($pathToMeta), true);

        return is_array($metaData) ? $metaData : new FailedToReadMetaJson;
    }

    public static function inventory(Game $game): void {
        $flagMetas = [];
        $metas = GameSharedStaticAsset::readMeta(public_path('res/bundled/flags/meta.json'));
        if (is_array($metas)) {
            $flagMetas = array_merge($flagMetas, $metas);
        }
        $metas = GameSharedStaticAsset::readMeta(public_path('res/local/flags/meta.json'));
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

    public function updateWithMetadata(SharedStaticAssetMetadata $metadata): void {
        $this->title = $metadata->title;
        $this->description = $metadata->description;
        $this->attribution = $metadata->attribution;
        $this->save();
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
