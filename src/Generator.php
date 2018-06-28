<?php

namespace Cws\EloquentModelGenerator;

use Cws\EloquentModelGenerator\Exception\GeneratorException;
use Cws\CodeGenerator\Model\ClassModel;

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
     * @return ClassModel
     * @throws GeneratorException
     */
    public function generateModel(Config $config)
    {
        $this->registerUserTypes($config);

        $model   = $this->builder->createModel($config);
        $content = $model->render();

        $outputPath = $this->resolveOutputPath($config);
        file_put_contents($outputPath, $content);

        return $model;
    }

    /**
     * @param Config $config
     * @return string
     * @throws GeneratorException
     */
    protected function resolveOutputPath(Config $config)
    {
        $path = $config->get('output_path');
        if ($path === null || stripos($path, '/') !== 0) {
            $path = app_path($path);
        }

        if (!is_dir($path)) {
            if (!mkdir($path, 0777, true)) {
                throw new GeneratorException(sprintf('Could not create directory %s', $path));
            }
        }

        if (!is_writeable($path)) {
            throw new GeneratorException(sprintf('%s is not writeable', $path));
        }

        return $path . '/' . $config->get('class_name') . '.php';
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
}
