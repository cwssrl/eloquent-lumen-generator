<?php

namespace Cws\EloquentModelGenerator;

use Cws\EloquentModelGenerator\Exception\GeneratorException;
use Cws\CodeGenerator\Model\ClassModel;
use Cws\EloquentModelGenerator\Model\EloquentModel;
use Cws\CodeGenerator\Model\ClassNameModel;
use Cws\CodeGenerator\Model\DocBlockModel;
use Cws\CodeGenerator\Model\MethodModel;
use Cws\CodeGenerator\Model\NamespaceModel;
use Cws\CodeGenerator\Model\UseClassModel;

/**
 * Class Generator
 * @package Cws\Generator
 */
class Generator
{
    /**
     * @var EloquentModelBuilder
     */
    protected $builder;

    /**
     * @var TypeRegistry
     */
    protected $typeRegistry;

    /**
     * Generator constructor.
     * @param EloquentModelBuilder $builder
     * @param TypeRegistry $typeRegistry
     */
    public function __construct(EloquentModelBuilder $builder, TypeRegistry $typeRegistry)
    {
        $this->builder = $builder;
        $this->typeRegistry = $typeRegistry;
    }

    /**
     * @param Config $config
     * @param EloquentModel|null $model
     * @param string $keyName
     * @param string|null $filename
     * @param bool $generateControllerAndRequest
     * @return EloquentModel
     * @throws GeneratorException
     */
    public function generateModel(
        Config $config,
        EloquentModel $model = null,
        string $keyName = "output_path",
        string $filename = null,
        $generateControllerAndRequest = false
    ) {
        $this->registerUserTypes($config);
        if (empty($model)) {
            $model = $this->builder->createModel($config);
        }
        $content = $model->render();

        $outputPath = $this->resolveOutputPath($config, $keyName, $filename);
        file_put_contents($outputPath, $content);

        if ($generateControllerAndRequest) {
            $this->createControllerForModelIfNeeded($config, $model);
        }

        return $model;
    }

    /**
     * @param Config $config
     * @param string $keyName
     * @param string|null $filename
     * @return string
     * @throws GeneratorException
     */
    protected function resolveOutputPath(Config $config, string $keyName = "output_path", string $filename = null)
    {
        $path = $config->get($keyName);
        if ($path === null || stripos($path, '/') !== 0) {
            $path = Misc::appPath($path);
        }

        if (!is_dir($path)) {
            if (!mkdir($path, 0777, true)) {
                throw new GeneratorException(sprintf('Could not create directory %s', $path));
            }
        }

        if (!is_writeable($path)) {
            throw new GeneratorException(sprintf('%s is not writeable', $path));
        }

        if (empty($filename)) {
            $filename = $config->get('class_name');
        }
        return $path . '/' . $filename . '.php';
    }

    /**
     * @param Config $config
     */
    protected function registerUserTypes(Config $config)
    {
        $userTypes = $config->get('db_types');
        if ($userTypes && is_array($userTypes)) {
            $connection = $config->get('connection');

            foreach ($userTypes as $type => $value) {
                $this->typeRegistry->registerType($type, $value, $connection);
            }
        }
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
            $config->checkIfFileAlreadyExistsOrCopyIt(
                $model,
                Misc::appPath("Http/Controllers"),
                "Controller.php",
                __DIR__ . '/Resources/Controllers',
                "BaseController.stub"
            );

            $modelFullPath = "\\" . $model->getNamespace()->getNamespace() . "\\" . $model->getName()->getName();
            //invoke the artisan command to create controller
            dump(
                exec(
                    (
                        "php artisan make:controller " .
                        $config->get('controller_path') .
                        "/" .
                        $model->getName()->getName() .
                        "Controller --model=$modelFullPath"
                    )
                )
            );
        }
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
        $pattern = Misc::appPath() . "/" . $modelName . "Controller.php";
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
