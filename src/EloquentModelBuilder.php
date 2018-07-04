<?php

namespace Cws\EloquentModelGenerator;

use Cws\CodeGenerator\Model\ClassNameModel;
use Cws\CodeGenerator\Model\DocBlockModel;
use Cws\CodeGenerator\Model\MethodModel;
use Cws\CodeGenerator\Model\NamespaceModel;
use Cws\CodeGenerator\Model\UseClassModel;
use Cws\EloquentModelGenerator\Exception\GeneratorException;
use Cws\EloquentModelGenerator\Model\EloquentModel;
use Cws\EloquentModelGenerator\Processor\ProcessorInterface;

/**
 * Class EloquentModelBuilder
 * @package Cws\EloquentModelGenerator
 */
class EloquentModelBuilder
{
    /**
     * @var ProcessorInterface[]
     */
    protected $processors;

    /**
     * EloquentModelBuilder constructor.
     * @param ProcessorInterface[] $processors
     */
    public function __construct($processors)
    {
        $this->processors = $processors;
    }

    /**
     * @param Config $config
     * @return EloquentModel
     * @throws GeneratorException
     */
    public function createModel(Config $config)
    {
        $model = new EloquentModel();

        $this->prepareProcessors();

        foreach ($this->processors as $processor) {
            $processor->process($model, $config);
        }
        $this->createControllerForModelIfNeeded($config, $model);
        $this->createRequestsForModelIfNeeded($config, $model);

        return $model;
    }

    /**
     * Sort processors by priority
     */
    protected function prepareProcessors()
    {
        usort($this->processors, function (ProcessorInterface $one, ProcessorInterface $two) {
            if ($one->getPriority() == $two->getPriority()) {
                return 0;
            }

            return $one->getPriority() < $two->getPriority() ? 1 : -1;
        });
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
            $modelFullPath = "\\" . $model->getNamespace()->getNamespace() . "\\" . $model->getName()->getName();
            //invoke the artisan command to create controller
            exec("php artisan make:controller " . $config->get('controller_path') . "/" . $model->getName()->getName() . "Controller --model=$modelFullPath");
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
}
