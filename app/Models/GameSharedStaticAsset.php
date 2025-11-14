<?php

namespace App\Models;

use App\Domain\SharedAssetType;
use App\Utils\GuardsForAssertions;
use App\Utils\MapsArrayToInstance;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Model;

readonly class SharedStaticAssetMetadata {
    use MapsArrayToInstance;

    public function __construct(
        public string $file,
        public ?string $title,
        public ?string $source,
        public ?string $description,
        public ?string $attribution,
        public ?string $license,
        public ?string $license_uri,
    )
    {
        
    }
}

class FailedToReadMetaJson {}

class GameSharedStaticAsset extends Model
{
    use GuardsForAssertions;

    public const string FIELD_ASSET_TYPE = 'type';

    public function getType(): SharedAssetType {
        return SharedAssetType::from($this->type);
    }

    public function getSrc(): string {
        return $this->src;
    }

    public function leaseTo(Nation $nation): void {
        $this->lessee_nation_id = $nation->getId();
        $this->save();
    }

    public static function getBySrcOrNull(string $uri): ?GameSharedStaticAsset {
        return GameSharedStaticAsset::where('src', $uri)->first();
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

    private static function readDirectory(string $pathToAssets, $reFilter): array {
        $fileMetas = [];
        foreach(array_filter(glob(public_path($pathToAssets) . "/*"), fn ($f) => preg_match($reFilter, $f)) as $filePath) {
            $file = join(DIRECTORY_SEPARATOR, [$pathToAssets, basename($filePath)]);
            
            $fileMetas[] = ["file" => $file];
        }
        
        return $fileMetas;
    }

    private static function inventoryAssetType(Game $game, SharedAssetType $sharedAssetType, string $reFileFilter): void {
        $subDirName = strtolower($sharedAssetType->name) . 's';
        $metas = [];
        $dirMetas = GameSharedStaticAsset::readMeta('res/bundled/' . $subDirName);
        if (is_array($dirMetas)) {
            $metas = array_merge($metas, $dirMetas);
        }
        $dirMetas = GameSharedStaticAsset::readMeta('res/local/' . $subDirName);
        if (is_array($metas)) {
            $metas = array_merge($metas, $dirMetas);
        }

        $metas = collect($metas)->mapWithKeys(fn ($m) => [$m['file'] => $m]);

        foreach(GameSharedStaticAsset::readDirectory('res/bundled/' . $subDirName, $reFileFilter) as $meta) {
            if (!$metas->has($meta['file'])) {
                $metas->put($meta['file'], $meta);
            }
        }
        foreach(GameSharedStaticAsset::readDirectory('res/local/flags' . $subDirName, $reFileFilter) as $meta) {
            if (!$metas->has($meta['file'])) {
                $metas->put($meta['file'], $meta);
            }
        }

        foreach($metas as $meta) {
            $metadata = SharedStaticAssetMetadata::fromArray($meta);

            $sharedAssetOrNull = $game->sharedAssetsOfType($sharedAssetType)
                ->where('src', $metadata->file)
                ->first();

            if (is_null($sharedAssetOrNull)) {
                GameSharedStaticAsset::create(
                    game: $game,
                    metadata: $metadata,
                    type: $sharedAssetType,
                );
            }

            $assetOrNull = AssetInfo::getBySrcOrNull($metadata->file);

            if (is_null($assetOrNull)) {
                AssetInfo::create(
                    src: $metadata->file,
                    title: $metadata->title,
                    source: $metadata->source,
                    description: $metadata->description,
                    attribution: $metadata->attribution,
                    license: $metadata->license,
                    license_uri: $metadata->license_uri,
                );
            }
            else {
                $asset = AssetInfo::notNull($assetOrNull);

                $asset->updateAsset(
                    title: $metadata->title,
                    source: $metadata->source,
                    description: $metadata->description,
                    attribution: $metadata->attribution,
                    license: $metadata->license,
                    license_uri: $metadata->license_uri,
                );
            }
        }
    }

    public static function inventory(Game $game): void {
        GameSharedStaticAsset::inventoryAssetType($game, SharedAssetType::Flag, '/\.(png|jpg|jpeg|gif)$/');
        GameSharedStaticAsset::inventoryAssetType($game, SharedAssetType::Leader, '/\.(png|jpg|jpeg|gif)$/');
        // $flagMetas = [];

        // $metas = GameSharedStaticAsset::readMeta('res/bundled/flags');
        // if (is_array($metas)) {
        //     $flagMetas = array_merge($flagMetas, $metas);
        // }
        // $metas = GameSharedStaticAsset::readMeta('res/local/flags');
        // if (is_array($metas)) {
        //     $flagMetas = array_merge($flagMetas, $metas);
        // }

        // $flagMetas = collect($flagMetas)->mapWithKeys(fn ($m) => [$m['file'] => $m]);

        // $reFlagImageFormats = '/\.(png|jpg|jpeg|gif)$/';
        // foreach(GameSharedStaticAsset::readDirectory("res/bundled/flags", $reFlagImageFormats) as $meta) {
        //     if (!$flagMetas->has($meta['file'])) {
        //         $flagMetas->put($meta['file'], $meta);
        //     }
        // }
        // foreach(GameSharedStaticAsset::readDirectory("res/local/flags", $reFlagImageFormats) as $meta) {
        //     if (!$flagMetas->has($meta['file'])) {
        //         $flagMetas->put($meta['file'], $meta);
        //     }
        // }

        // foreach($flagMetas as $meta) {
        //     $metadata = SharedStaticAssetMetadata::fromArray($meta);

        //     $sharedAssetOrNull = $game->sharedAssetsOfType(SharedAssetType::Flag)
        //         ->where('src', $metadata->file)
        //         ->first();

        //     if (is_null($sharedAssetOrNull)) {
        //         GameSharedStaticAsset::create(
        //             game: $game,
        //             metadata: $metadata,
        //             type: SharedAssetType::Flag,
        //         );
        //     }

        //     $assetOrNull = AssetInfo::getBySrcOrNull($metadata->file);

        //     if (is_null($assetOrNull)) {
        //         AssetInfo::create(
        //             src: $metadata->file,
        //             title: $metadata->title,
        //             description: $metadata->description,
        //             attribution: $metadata->attribution,
        //             license: $metadata->license,
        //             license_uri: $metadata->license_uri,
        //         );
        //     }
        //     else {
        //         $asset = AssetInfo::notNull($assetOrNull);

        //         $asset->updateAsset(
        //             title: $metadata->title,
        //             description: $metadata->description,
        //             attribution: $metadata->attribution,
        //             license: $metadata->license,
        //             license_uri: $metadata->license_uri,
        //         );
        //     }
        // }
    }

    public static function create(
        Game $game,
        SharedStaticAssetMetadata $metadata,
        SharedAssetType $type,
    ): GameSharedStaticAsset {
        $asset = new GameSharedStaticAsset();
        $asset->game_id = $game->getId();
        $asset->src = $metadata->file;
        $asset->type = $type->value;
        $asset->save();
        
        return $asset;
    }
}
