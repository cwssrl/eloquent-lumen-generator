<?php

namespace Cws\EloquentModelGenerator\Processor;

use Cws\EloquentModelGenerator\Misc;
use Cws\EloquentModelGenerator\Config;
use Cws\EloquentModelGenerator\Model\EloquentModel;

/**
 * Class NamespaceProcessor
 * @package Cws\EloquentModelGenerator\Processor
 */
class AdditionalProcessor implements ProcessorInterface
{
    /**
     * @inheritdoc
     */
    public function process(EloquentModel $model, Config $config)
    {
        if (!Misc::endsWith($model->getTableName(), "_translations")) {
            $this->createRoutesForModelIfNeeded($config, $model);
            $this->createRoutesForModelIfNeeded($config, $model, true);
            $this->createApiResourceForModelIfNeeded($config, $model);
            $this->createApiControllerForModelIfNeeded($config, $model);
        }
        return $this;
    }

    /**
     * If routes key in config is not false creates the resources routes for current model
     *
     * @param Config $config
     * @param EloquentModel $model
     */
    private function createRoutesForModelIfNeeded(Config $config, EloquentModel $model, bool $isApi = false)
    {
        if ((!$isApi && $config->get("routes") !== false) || ($isApi && $config->get("api_routes") !== false)) {
            $controllerPath = $config->get('controller_path');
            if ($isApi) {
                $controllerPath .= (empty($controllerPath) ? "API" : "\API");
            }

            //build the route line that we need to add to routes file looking for the controller path
            $resType = $isApi ? "Route::apiResource(\"" : "Route::resource(\"";
            $command = $resType .
                $model->getTableName() . "\", '" .
                (empty($controllerPath) ? "" : ($controllerPath . "\\"))
                . $model->getName()->getName() . ($isApi ? "API" : "") . "Controller');";
            $path = $isApi ? $config->get("api_routes_path") : $config->get("routes_path");
            //get the route file content and check if our line does not already exist
            $content = file_get_contents($path);
            if (strpos($content, $command) === false) {
                $content .= PHP_EOL . PHP_EOL . $command;
                file_put_contents($path, $content);
            }
        }
    }

    /**
     * If controller key in config is not false creates the controller for current model
     *
     * @param Config $config
     * @param EloquentModel $model
     */
    private function createApiResourceForModelIfNeeded(Config $config, EloquentModel $model)
    {
        if ($config->get("resource") !== false) {
            //invoke the artisan command to create controller
            $config->checkIfFileAlreadyExistsOrCopyIt($model, Misc::appPath("Http/Resources"),
                $model->getName()->getName() . "Resource.php",
                __DIR__ . '/../Resources/Resources', "Resource.stub");

            $config->checkIfFileAlreadyExistsOrCopyIt($model, Misc::appPath("Http/Resources"),
                "RestResourceCollection.php",
                __DIR__ . '/../Resources/Api', "RestResourceCollection.php.stub");
            $config->checkIfFileAlreadyExistsOrCopyIt($model, Misc::appPath("Http/Resources"),
                $model->getName()->getName() . "CollectionResource.php",
                __DIR__ . '/../Resources/Resources', "ResourceCollection.stub");

            $collectionContent = file_get_contents(Misc::appPath("Http/Resources/" . $model->getName()->getName() . "CollectionResource.php"));
            $collectionContent = str_replace("use Illuminate\Http\Resources\Json\ResourceCollection;", "", $collectionContent);
            $collectionContent = str_replace("extends ResourceCollection", "extends RestResourceCollection", $collectionContent);
            file_put_contents(Misc::appPath("Http/Resources/" . $model->getName()->getName() . "CollectionResource.php"), $collectionContent);
        }
    }

    /**
     * If controller key in config is not false creates the controller for current model
     *
     * @param Config $config
     * @param EloquentModel $model
     */
    private function createApiControllerForModelIfNeeded(Config $config, EloquentModel $model)
    {
        if ($config->get("api-controller") !== false) {
            $config->checkIfFileAlreadyExistsOrCopyIt($model, Misc::appPath("Http/Controllers"),
                "Controller.php",
                __DIR__ . '/../Resources/Controllers', "BaseController.stub");

            $apiControllersFolder = Misc::appPath("Http/Controllers/API");
            if (!is_dir($apiControllersFolder))
                mkdir($apiControllersFolder);
            $config->checkIfFileAlreadyExistsOrCopyIt($model, Misc::appPath("Http/Controllers/API"),
                "APIBaseController.php",
                __DIR__ . '/../Resources/Controllers', "ApiBaseController.stub");
            $config->checkIfFileAlreadyExistsOrCopyIt($model, Misc::appPath("Http/Controllers/API"),
                $model->getName()->getName() . "APIController.php",
                __DIR__ . '/../Resources/Controllers', "ApiModelController.stub");
            $traitsFolder = Misc::appPath("Traits");
            if (!is_dir($traitsFolder))
                mkdir($traitsFolder);

            $config->checkIfFileAlreadyExistsOrCopyIt($model, $traitsFolder,
                "RestExceptionHandlerTrait.php",
                __DIR__ . '/../Resources/Traits', "RestExceptionHandlerTrait.php.stub");
            $config->checkIfFileAlreadyExistsOrCopyIt($model, $traitsFolder,
                "RestTrait.php",
                __DIR__ . '/../Resources/Traits', "RestTrait.php.stub");
            $config->checkIfFileAlreadyExistsOrCopyIt($model, Misc::appPath("Exceptions"),
                "Handler.php",
                __DIR__ . '/../Resources/Traits', "Handler.php.stub", true);

        }
    }

    /**
     * @inheritdoc
     */
    public function getPriority()
    {
        return 0;
    }
}
