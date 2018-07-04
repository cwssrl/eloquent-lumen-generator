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
        $this->createApiResourceForModelIfNeeded($config, $model);
        return $this;
    }

    /**
     * If controller key in config is not false creates the controller for current model
     *
     * @param Config $config
     * @param EloquentModel $model
     */
    private function createControllerForModelIfNeeded(Config $config, EloquentModel $model)
    {
        if ($config->get("controller") !== false) {
            //invoke the artisan command to create controller
            exec("php artisan make:controller " . $config->get('controller_path') . "/" . $model->getName()->getName() . "Controller --resource");
        }
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
     * If request key in config is not false creates the create and update requests for current model
     *
     * @param Config $config
     * @param EloquentModel $model
     */
    private function createRequestsForModelIfNeeded(Config $config, EloquentModel $model)
    {
        if ($config->get("request") !== false) {
            $this->createRequest($config, $model);
            $this->createRequest($config, $model, "Update");
        }
    }

    /**
     * Create a form request for current model
     *
     * @param Config $config
     * @param EloquentModel $model
     * @param string $requestNamePrefix
     */
    private function createRequest(Config $config, EloquentModel $model, string $requestNamePrefix = "Create")
    {
        $modelName = $model->getName()->getName();
        $a = new EloquentModel();

        //set the request namespace reading it from config
        $a->setNamespace(new NamespaceModel($config->get("request_namespace")));

        //add uses to our request
        $a->addUses(new UseClassModel("Illuminate\Foundation\Http\FormRequest"));
        $a->addUses(new UseClassModel($model->getNamespace()->getNamespace() . "\\" . $modelName));

        $requestName = $requestNamePrefix . $modelName . "Request";
        $a->setName(new ClassNameModel($requestName, "FormRequest"));

        //add the authorize method to our request
        $method = new MethodModel("authorize");
        $method->setDocBlock(new DocBlockModel("Get the validation rules that apply to the request."
            . PHP_EOL . PHP_EOL
            . "\t * @return bool"));
        $method->setBody("return true;");
        $a->addMethod($method);

        //add the rules method to our request using the rules we've added to its model to validate
        $method = new MethodModel("rules");
        $method->setDocBlock(new DocBlockModel("Determine if the user is authorized to make this request."
            . PHP_EOL
            . "\t * @return array"));
        $method->setBody("return " . $modelName . '::$rules;');
        $a->addMethod($method);

        $this->updateControllerFile($modelName, $requestNamePrefix,
            "\\" . $a->getNamespace()->getNamespace() . "\\" . $a->getName()->getName());
        //render the model
        $generator = app("Cws\EloquentModelGenerator\Generator");
        $generator->generateModel($config, $a, "request_path", $requestName);
    }

    /**
     * Update the controllers method parameters with new requests
     *
     * @param $modelName
     * @param $requestNamePrefix
     * @param $requestFullClassPath
     */
    private function updateControllerFile($modelName, $requestNamePrefix, $requestFullClassPath)
    {
        $pattern = app_path() . "/" . $modelName . "Controller.php";
        $controllersFiles = $this->recursiveGlob($pattern);
        if (count($controllersFiles)) {
            $stringToSearch = $stringToReplaceWith = null;
            switch (strtolower($requestNamePrefix)) {
                case "create":
                    $stringToSearch = "public function store(Request";
                    $stringToReplaceWith = "public function store(" . $requestFullClassPath;
                    break;
                case "update":
                    $stringToSearch = "public function update(Request";
                    $stringToReplaceWith = "public function update(" . $requestFullClassPath;
                    break;
            }

            foreach ($controllersFiles as $cf) {
                $content = file_get_contents($cf);
                $content = str_replace($stringToSearch, $stringToReplaceWith, $content);
                file_put_contents($cf, $content);
            }
        }
    }

    /**
     * Search for all files with pattern in folder and subfolders
     *
     * @param $pattern
     * @return array
     */
    private function recursiveGlob($pattern)
    {
        $first_files = glob($pattern);
        foreach (glob(dirname($pattern) . '/*') as $dir) {
            $first_files = array_merge($first_files, $this->recursiveGlob($dir . '/' . basename($pattern)));
        }
        return $first_files;
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
