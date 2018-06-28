<?php

namespace Cws\EloquentModelGenerator\Provider;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Cws\EloquentModelGenerator\Command\GenerateModelCommand;
use Cws\EloquentModelGenerator\EloquentModelBuilder;
use Cws\EloquentModelGenerator\Processor\CustomPrimaryKeyProcessor;
use Cws\EloquentModelGenerator\Processor\CustomPropertyProcessor;
use Cws\EloquentModelGenerator\Processor\ExistenceCheckerProcessor;
use Cws\EloquentModelGenerator\Processor\FieldProcessor;
use Cws\EloquentModelGenerator\Processor\NamespaceProcessor;
use Cws\EloquentModelGenerator\Processor\RelationProcessor;
use Cws\EloquentModelGenerator\Processor\TableNameProcessor;

/**
 * Class GeneratorServiceProvider
 * @package Cws\EloquentModelGenerator\Provider
 */
class GeneratorServiceProvider extends ServiceProvider
{
    const PROCESSOR_TAG = 'eloquent_model_generator.processor';
    /**
     * {@inheritDoc}
     */
    public function register()
    {
        $this->commands([
            GenerateModelCommand::class,
        ]);

        $this->app->tag([
            ExistenceCheckerProcessor::class,
            FieldProcessor::class,
            NamespaceProcessor::class,
            RelationProcessor::class,
            CustomPropertyProcessor::class,
            TableNameProcessor::class,
            CustomPrimaryKeyProcessor::class,
        ], self::PROCESSOR_TAG);

        $this->app->bind(EloquentModelBuilder::class, function (Application $app) {
            return new EloquentModelBuilder($app->tagged(self::PROCESSOR_TAG));
        });
    }
}