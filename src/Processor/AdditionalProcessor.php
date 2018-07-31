<?php

namespace Cws\EloquentModelGenerator\Processor;

use Cws\CodeGenerator\Model\ClassNameModel;
use Cws\CodeGenerator\Model\DocBlockModel;
use Cws\CodeGenerator\Model\MethodModel;
use Cws\CodeGenerator\Model\NamespaceModel;
use Cws\CodeGenerator\Model\UseClassModel;
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
        if (!ends_with($model->getTableName(), "_translations")) {
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
            if ($isApi)
                $controllerPath .= (empty($controllerPath) ? "API" : "\API");

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
            exec("php artisan make:resource " . $model->getName()->getName() . "Resource");
            $config->checkIfFileAlreadyExistsOrCopyIt($model, app_path("Http/Resources"),
                "RestResourceCollection.php",
                __DIR__ . '/../Resources/Api', "RestResourceCollection.php.stub");
            exec("php artisan make:resource " . $model->getName()->getName() . "CollectionResource --collection");
            $collectionContent = file_get_contents(app_path("Http/Resources/" . $model->getName()->getName() . "CollectionResource.php"));
            $collectionContent = str_replace("use Illuminate\Http\Resources\Json\ResourceCollection;", "", $collectionContent);
            $collectionContent = str_replace("extends ResourceCollection", "extends RestResourceCollection", $collectionContent);
            file_put_contents(app_path("Http/Resources/" . $model->getName()->getName() . "CollectionResource.php"), $collectionContent);
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
            $config->checkIfFileAlreadyExistsOrCopyIt($model, app_path("Http/Controllers"),
                "Controller.php",
                __DIR__ . '/../Resources/Controllers', "BaseController.stub");

            $apiControllersFolder = app_path("Http/Controllers/API");
            if (!is_dir($apiControllersFolder))
                mkdir($apiControllersFolder);
            $config->checkIfFileAlreadyExistsOrCopyIt($model, app_path("Http/Controllers/API"),
                "APIBaseController.php",
                __DIR__ . '/../Resources/Controllers', "ApiBaseController.stub");
            $config->checkIfFileAlreadyExistsOrCopyIt($model, app_path("Http/Controllers/API"),
                $model->getName()->getName() . "APIController.php",
                __DIR__ . '/../Resources/Controllers', "ApiModelController.stub");
            $traitsFolder = app_path("Traits");
            if (!is_dir($traitsFolder))
                mkdir($traitsFolder);
            $config->checkIfFileAlreadyExistsOrCopyIt($model, $traitsFolder,
                "RestExceptionHandlerTrait.php",
                __DIR__ . '/../Resources/Traits', "RestExceptionHandlerTrait.php.stub");
            $config->checkIfFileAlreadyExistsOrCopyIt($model, $traitsFolder,
                "RestTrait.php",
                __DIR__ . '/../Resources/Traits', "RestTrait.php.stub");
            $config->checkIfFileAlreadyExistsOrCopyIt($model, app_path("Exceptions"),
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
