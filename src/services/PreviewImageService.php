<?php

namespace weareferal\matrixfieldpreview\services;

use weareferal\matrixfieldpreview\MatrixFieldPreview;
use weareferal\matrixfieldpreview\records\PreviewRecord;

use Craft;
use craft\base\Component;
use craft\helpers\Assets as AssetsHelper;
use craft\elements\Asset;
use craft\errors\VolumeException;
use craft\helpers\Image;
use craft\errors\ImageException;
use craft\errors\InvalidSubpathException;


class PreviewImageService extends Component
{
    /**
     * Save a new Asset from our upload and assign to block type Record
     * 
     * Heavily inspired by services/Users.saveUserPhoto
     * 
     * https://github.com/craftcms/cms/blob/develop/src/services/Users.php#L408
     */
    public function savePreviewImage(string $fileLocation, $preview, string $filename = '')
    {
        $settings = MatrixFieldPreview::getInstance()->getSettings();

        $filenameToUse = AssetsHelper::prepareAssetName($filename ?: pathinfo($fileLocation, PATHINFO_FILENAME), true, true);

        if (!Image::canManipulateAsImage(pathinfo($fileLocation, PATHINFO_EXTENSION))) {
            throw new ImageException(Craft::t('matrix-field-preview', 'Preview image must be an image that Craft can manipulate.'));
        }
        $volumes = Craft::$app->getVolumes();
        $volumeUid = $settings->previewVolumeUid;

        if (!$volumeUid || ($volume = $volumes->getVolumeByUid($volumeUid)) === null) {
            throw new VolumeException(Craft::t(
                'matrix-field-preview',
                'The volume set for preview image storage is not valid.'
            ));
        }

        $subpath = (string) $settings->previewSubpath;

        if ($subpath) {
            try {
                $subpath = Craft::$app->getView()->renderObjectTemplate($subpath, $preview);
            } catch (\Throwable $e) {
                throw new InvalidSubpathException($subpath);
            }
        }

        $assetsService = Craft::$app->getAssets();

        if ($preview->previewImageId && $preview->getPreviewImage() !== null) {
            $assetsService->replaceAssetFile($assetsService->getAssetById($preview->previewImageId), $fileLocation, $filenameToUse);
        } else {
            Craft::info('Test123', 'matrix-field-preview-log');
            Craft::info($subpath, 'matrix-field-preview-log');
            Craft::info($volume, 'matrix-field-preview-log');
            $folderId = $assetsService->ensureFolderByFullPathAndVolume($subpath, $volume);
            $filenameToUse = $assetsService->getNameReplacementInFolder($filenameToUse, $folderId);

            // Create new element
            $previewImage = new Asset();
            $previewImage->setScenario(Asset::SCENARIO_CREATE);
            $previewImage->tempFilePath = $fileLocation;
            $previewImage->filename = $filenameToUse;
            $previewImage->newFolderId = $folderId;
            $previewImage->volumeId = $volume->id;

            // Save photo.
            $elementsService = Craft::$app->getElements();
            $elementsService->saveElement($previewImage);

            // Save preview image to our Record's FK
            $preview->setPreviewImage($previewImage);
            $preview->save();
        }
    }
}
