<?php

namespace App\Facades;

use App\Utils\ImageSource;
use ErrorException;
use Exception;
use GdImage;
use Illuminate\Http\UploadedFile;

readonly class ImageProcessingError {
    public function __construct(
        public string $message,
    )
    {
        
    }

    public static function fromException(string $message, Exception $e): ImageProcessingError {
        return new ImageProcessingError($message . ": " . $e->getMessage());
    }
}

/**
 * Helper class to resize and store uploaded images.
 */
final class ImageProcessing {
    /**
     * Doesn't allow instanciation.
     */
    private function __construct()
    {

    }

    /**
     * Returns the first file if there are multiple files, the lone file or null if the argument is null (a file wasn't uploaded).
     */
    public static function getFileOrNull(array|UploadedFile|null $filesOrFileOrNull): ?UploadedFile {
        return match(true) {
            is_array($filesOrFileOrNull) => reset($filesOrFileOrNull),
            $filesOrFileOrNull instanceof UploadedFile => $filesOrFileOrNull,
            is_null($filesOrFileOrNull) => null,
        };
    }

    /**
     * Crop to fit the uploaded image and store it in the public storage at the specified destination. It should allow a client to fetch the image by using the destination as a relative URI.
     */
    public static function cropFitToPublicImage(UploadedFile $imageFile, int $targetImageWidthPixels, int $targetImageHeightPixels, string $destinationSrc): ImageSource|ImageProcessingError {
        $tmpFilePath = $imageFile->getRealPath();
        if ($tmpFilePath === false) {
            return new ImageProcessingError('Unable to read uploaded image.');
        }
        try {
            $imageSize = getimagesize($tmpFilePath);
            if ($imageSize === false) {
                return new ImageProcessingError('Unable to read uploaded image.');
            }
            list($originalWidthPixels, $originalHeightPixels) = $imageSize;
            $originalImage = imagecreatefromstring($imageFile->getContent());
            if ($originalImage === false) {
                return new ImageProcessingError('Unable to read uploaded image.');
            }
            $destinationImage = imagecreatetruecolor($targetImageWidthPixels, $targetImageHeightPixels);
        }
        catch(ErrorException $e) {
            return ImageProcessingError::fromException('Unable to read uploaded image', $e);
        }
        asset($originalImage instanceof GdImage);
        asset($destinationImage instanceof GdImage);
        $originalAspectRatio = $originalWidthPixels / $originalHeightPixels;
        $targetAspectRatio = $targetImageWidthPixels / $targetImageHeightPixels;

        if ($originalAspectRatio > $targetAspectRatio) {
            // Source is wider, crop width
            $sourceImageWidthPixels = floor($originalHeightPixels * $targetAspectRatio);
            $sourceImageHeightPixels = $originalHeightPixels;
            $sourceX = floor(($originalWidthPixels - $sourceImageWidthPixels) / 2);
            $sourceY = 0;
        }
        else if ($originalAspectRatio < $targetAspectRatio) {
            // source is taller, crop height
            $sourceImageWidthPixels = $originalWidthPixels;
            $sourceImageHeightPixels = floor($originalWidthPixels / $targetAspectRatio);
            $sourceX = 0;
            $sourceY = floor(($originalHeightPixels - $sourceImageHeightPixels) / 2);
        }
        else {
            // equal, no crop needed
            $sourceImageWidthPixels = $originalWidthPixels;
            $sourceImageHeightPixels = $originalHeightPixels;
            $sourceX = 0;
            $sourceY = 0;
        }

        // $horizontalRatio = $targetImageWidthPixels / $originalWidthPixels;
        // $verticalRatio = $targetImageHeightPixels / $originalHeightPixels;
        // $bestRatio = min($horizontalRatio, $verticalRatio);
        // $croppedImageWidthPixels = round($targetImageWidthPixels * $bestRatio);
        // $croppedImageHeightPixels = round($targetImageHeightPixels * $bestRatio);

        // $sourceX = floor(($originalWidthPixels - $croppedImageWidthPixels) / 2);
        // $sourceY = floor(($originalHeightPixels - $croppedImageHeightPixels) / 2);

        // $necessaryToFirstResizeOriginalImage = $tempImageWidthPixels != $originalWidthPixels || $tempImageHeightPixels != $originalHeightPixels;
        // if ($necessaryToFirstResizeOriginalImage) {

        // }

        $resizeResult = imagecopyresampled($destinationImage, $originalImage, 0, 0, $sourceX, $sourceY, $targetImageWidthPixels, $targetImageHeightPixels, $sourceImageWidthPixels, $sourceImageHeightPixels);
        if ($resizeResult === false) {
            return new ImageProcessingError('Unable to resize uploaded image.');
        }
        $saveResult = imagepng($destinationImage, public_path($destinationSrc));
        if ($saveResult === false) {
            return new ImageProcessingError('Unable to store uploaded image.');
        }
            
        return ImageSource::fromSrc($destinationSrc);
    }
}