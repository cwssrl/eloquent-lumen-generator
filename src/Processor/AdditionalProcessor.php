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
        $this->createRoutesForModelIfNeeded($config, $model);
        $this->createApiResourceForModelIfNeeded($config, $model);
        return $this;
    }

    /**
     * If routes key in config is not false creates the resources routes for current model
     *
     * @param Config $config
     * @param EloquentModel $model
     */
    private function createRoutesForModelIfNeeded(Config $config, EloquentModel $model)
    {
        if ($config->get("routes") !== false) {
            $controllerPath = $config->get('controller_path');

            //build the route line that we need to add to routes file looking for the controller path
            $command = "Route::resource(\"" .
                $model->getTableName() . "\", '" .
                (empty($controllerPath) ? "" : ($controllerPath . "/"))
                . $model->getName()->getName() . "Controller');";
            $path = $config->get("routes_path");

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
            exec("php artisan make:resource " . $model->getName()->getName());
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
