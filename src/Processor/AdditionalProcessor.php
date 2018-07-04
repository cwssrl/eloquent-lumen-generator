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
        $this->createControllerForModelIfNeeded($config, $model);
        $this->createRoutesForModelIfNeeded($config, $model);
        $this->createRequestsForModelIfNeeded($config, $model);
        return $this;
    }

    private function createControllerForModelIfNeeded(Config $config, EloquentModel $model)
    {
        if ($config->get("controller") !== false) {
            exec("php artisan make:controller " . $config->get('controller_path') . "/" . $model->getName()->getName() . "Controller --resource");
        }
    }

    private function createRoutesForModelIfNeeded(Config $config, EloquentModel $model)
    {
        if ($config->get("routes") !== false) {
            $controllerPath = $config->get('controller_path');
            $command = "Route::resource(\"" .
                $model->getTableName() . "\", '" .
                (empty($controllerPath) ? "" : ($controllerPath . "/"))
                . $model->getName()->getName() . "Controller');";
            $path = $config->get("routes_path");
            $content = file_get_contents($path);
            if (strpos($content, $command) === false) {
                $content .= PHP_EOL . PHP_EOL . $command;
                file_put_contents($path, $content);
            }
        }
    }

    private function createRequestsForModelIfNeeded(Config $config, EloquentModel $model)
    {
        if ($config->get("request") !== false) {
            $this->createRequest($config, $model);
            $this->createRequest($config, $model, "Update");
        }
    }

    private function createRequest(Config $config, EloquentModel $model, string $requestNamePrefix = "Create")
    {
        $modelName = $model->getName()->getName();
        $a = new EloquentModel();
        $a->setNamespace(new NamespaceModel($config->get("request_namespace")));
        $a->addUses(new UseClassModel("Illuminate\Foundation\Http\FormRequest"));
        $a->addUses(new UseClassModel($model->getNamespace()->getNamespace() . "\\" . $modelName));
        $requestName = $requestNamePrefix . $modelName . "Request";
        $a->setName(new ClassNameModel($requestName, "FormRequest"));

        $method = new MethodModel("authorize");
        $method->setDocBlock(new DocBlockModel("Get the validation rules that apply to the request."
            . PHP_EOL . PHP_EOL
            . "\t * @return bool"));
        $method->setBody("return true;");
        $a->addMethod($method);

        $method = new MethodModel("rules");
        $method->setDocBlock(new DocBlockModel("Determine if the user is authorized to make this request."
            . PHP_EOL
            . "\t * @return array"));
        $method->setBody("return " . $modelName . '::$rules;');
        $a->addMethod($method);
        $generator = app("Cws\EloquentModelGenerator\Generator");
        $generator->generateModel($config, $a, "request_path", $requestName);

    }

    /**
     * @inheritdoc
     */
    public function getPriority()
    {
        return 0;
    }
}
