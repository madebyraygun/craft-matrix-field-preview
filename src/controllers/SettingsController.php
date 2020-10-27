<?php


namespace weareferal\matrixfieldpreview\controllers;

use weareferal\matrixfieldpreview\MatrixFieldPreview;
use weareferal\matrixfieldpreview\assets\matrixfieldpreviewsettings\MatrixFieldPreviewSettingsAsset;
use weareferal\matrixfieldpreview\records\BlockTypeConfigRecord;

use Craft;
use craft\web\Controller;


class SettingsController extends Controller
{

    protected $allowAnonymous = [];

    /**
     * General plugin settings
     */
    public function actionGeneral()
    {
        $plugin = MatrixFieldPreview::getInstance();
        $settings = $plugin->getSettings();

        $this->view->registerAssetBundle(MatrixFieldPreviewSettingsAsset::class);

        return $this->renderTemplate('matrix-field-preview/settings/general', [
            'settings' => $settings,
            'plugin' => $plugin
        ]);
    }

    /**
     * Enable/disable previews on matrix fields
     */
    public function actionFields()
    {
        $plugin = MatrixFieldPreview::getInstance();
        $settings = $plugin->getSettings();
        $request = Craft::$app->request;

        $this->view->registerAssetBundle(MatrixFieldPreviewSettingsAsset::class);

        // Get all sections and matrix fields
        $sections = Craft::$app->sections->getAllSections();

        if ($request->isPost) {
            $post = $request->post();
            foreach ($post['settings'] as $handle => $values) {
                $fieldConfig = $plugin->fieldConfigService->getByHandle($handle);
                if ($fieldConfig) {
                    $fieldConfig->enablePreviews = $values['enablePreviews'];
                    $fieldConfig->enableTakeover = $values['enableTakeover'];
                    if ($fieldConfig->validate()) {
                        $fieldConfig->save();
                    }
                }
            }
        }

        $matrixFields = $plugin->blockTypeConfigService->getAllMatrixFields();
        $fieldConfigs = $plugin->fieldConfigService->getAll();

        usort($fieldConfigs, function ($a, $b) {
            return strcmp($a->field->name, $b->field->name);
        });

        // Craft::info($sections, "matrix-field-previews");

        return $this->renderTemplate('matrix-field-preview/settings/fields', [
            'settings' => $settings,
            'plugin' => $plugin,
            'matrixFields' => $matrixFields,
            'fieldConfigs' => $fieldConfigs
        ]);
    }

    /**
     * Add images and descriptions to individual matrix field block types
     */
    public function actionBlockTypes()
    {
        $plugin = MatrixFieldPreview::getInstance();
        $settings = $plugin->getSettings();

        $this->view->registerAssetBundle(MatrixFieldPreviewSettingsAsset::class);

        $assets = [
            'success' => Craft::$app->getAssetManager()->getPublishedUrl('@app/web/assets/cp/dist', true, 'images/success.png')
        ];

        $blockTypes = Craft::$app->matrix->getAllBlockTypes();
        $blockTypeConfigs = $plugin->blockTypeConfigService->getAll();

        $blockTypeConfigMap = [];
        foreach ($blockTypeConfigs as $blockTypeConfig) {
            $blockTypeConfigMap[$blockTypeConfig->blockType->id] = $blockTypeConfig;
        }

        $matrixFieldsMap = [];
        foreach ($blockTypes as $blockType) {
            $matrixField = $blockType->field;

            $fieldConfig = $plugin->fieldConfigService->getByHandle($matrixField->handle);

            // Initialise an array for each matrix field
            if (!array_key_exists($matrixField->id, $matrixFieldsMap)) {
                $matrixFieldsMap[$matrixField->id] = [
                    'matrixField' => $matrixField,
                    'fieldConfig' => $fieldConfig,
                    'rows' => []
                ];
            }

            // Get the block type config for this block type if it exists
            $blockTypeConfig = null;
            if (array_key_exists($blockType->id, $blockTypeConfigMap)) {
                $blockTypeConfig = $blockTypeConfigMap[$blockType->id];
            }

            array_push($matrixFieldsMap[$matrixField->id]['rows'], [
                'blockType' => $blockType,
                'blockTypeConfig' => $blockTypeConfig
            ]);
        }

        $matrixFields = [];
        foreach ($matrixFieldsMap as $key => $value) {
            array_push($matrixFields, $value);
        }

        return $this->renderTemplate('matrix-field-preview/settings/block-types', [
            'settings' => $settings,
            'plugin' => $plugin,
            'assets' => $assets,
            'matrixFields' => $matrixFields
        ]);
    }

    /**
     * Configure an individual matrix field block type preview. Upload an
     * image and a custom description
     */
    public function actionBlockType($blockTypeId)
    {
        $this->view->registerAssetBundle(MatrixFieldPreviewSettingsAsset::class);

        $siteId = Craft::$app->getSites()->currentSite->id;
        $request = Craft::$app->request;
        $plugin = MatrixFieldPreview::getInstance();
        $settings = $plugin->getSettings();

        $blockType = Craft::$app->matrix->getBlockTypeById($blockTypeId);
        if (!$blockType) {
            throw new NotFoundHttpException('Invalid matrix block type ID: ' . $blockTypeId);
        }

        $blockTypeConfig = $plugin->blockTypeConfigService->getByBlockTypeId($blockTypeId);
        if (!$blockTypeConfig) {
            $blockTypeConfig = new BlockTypeConfigRecord();
            $blockTypeConfig->siteId = $siteId;
            $blockTypeConfig->description = "";
            $blockTypeConfig->fieldId = $blockType->field->id;
            $blockTypeConfig->blockTypeId = $blockType->id;
            $blockTypeConfig->save();
        }

        if ($request->isPost) {
            $post = $request->post();
            $blockTypeConfig->description = $post['description'];
            if ($blockTypeConfig->validate()) {
                $blockTypeConfig->save();
                Craft::$app->getSession()->setNotice(Craft::t('app', 'Preview saved.'));
                return $this->redirect('matrix-field-preview/settings/block-types');
            } else {
                Craft::$app->getSession()->setError(Craft::t('app', 'Couldn’t save preview.'));
            }
        }

        return $this->renderTemplate(
            'matrix-field-preview/settings/block-type',
            [
                'blockTypeConfig' => $blockTypeConfig,
                'plugin' => $plugin,
                'fullPageForm' => true,
                'settings' => $settings
            ]
        );
    }
}
