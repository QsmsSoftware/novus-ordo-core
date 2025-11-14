<?php

namespace App\Models;

use App\ReadModels\AssetPublicInfo;
use App\Utils\GuardsForAssertions;
use Illuminate\Database\Eloquent\Model;

class AssetInfo extends Model
{
    use GuardsForAssertions;
    
    public function getSrc(): string {
        return $this->src;
    }

    public function updateAsset(
        ?string $title,
        ?string $source,
        ?string $description,
        ?string $attribution,
        ?string $license,
        ?string $license_uri,
    ): void {
        $this->title = $title;
        $this->source = $source;
        $this->description = $description;
        $this->attribution = $attribution;
        $this->license = $license;
        $this->license_uri = $license_uri;
        $this->save();

        AssetInfo::where('original_src', $this->src)
            ->whereNot('id', $this->id)
            ->get()
            ->each(fn (AssetInfo $asset) => $asset->updateAsset(
                title: $this->title,
                source: $this->source,
                description: $this->description,
                attribution: $this->attribution,
                license: $this->license,
                license_uri: $this->license_uri,
            ));
    }

    public function exportInfo(): AssetPublicInfo {
        return new AssetPublicInfo(
            uri: $this->getSrc(),
            title: $this->title,
            source: $this->source,
            description: $this->description,
            attribution: $this->attribution,
            license: $this->license,
            license_uri: $this->license_uri,
        );
    }

    public function createFrom(
        string $newSrc,
    ): AssetInfo {
        $asset = $this->replicate();
        $asset->src = $newSrc;
        //$asset->original_src = $this->src;
        $asset->save();
        
        return $asset;
    }

    public static function getBySrcOrNull(string $src): ?AssetInfo {
        return AssetInfo::where('src', $src)->first();
    }

    public static function create(
        string $src,
        ?string $title,
        ?string $source,
        ?string $description,
        ?string $attribution,
        ?string $license,
        ?string $license_uri,
    ): AssetInfo {
        $asset = new AssetInfo();
        $asset->src = $src;
        $asset->original_src = $src;
        $asset->title = $title;
        $asset->source = $source;
        $asset->description = $description;
        $asset->attribution = $attribution;
        $asset->license = $license;
        $asset->license_uri = $license_uri;
        $asset->save();
        
        return $asset;
    }
}
