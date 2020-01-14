<?php

namespace Cws\EloquentModelGenerator;

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
        $config->checkIfFileAlreadyExistsOrCopyIt(
            $model,
            Misc::appPath("Models"),
            "BaseModel.php.stub",
            __DIR__ . '/Resources/Models',
            "BaseModel.php"
        );
        return $model;
    }

    /**
     * Sort processors by priority
     */
    protected function prepareProcessors()
    {
        $temp = [];
        $current = null;
        if (!is_array($this->processors)) {
            $iterator = $this->processors->getIterator();
            while (!empty($current = $iterator->current())) {
                array_push($temp, $current);
                $iterator->next();
            }
            $this->processors = $temp;
        }
        usort($this->processors, function (ProcessorInterface $one, ProcessorInterface $two) {
            if ($one->getPriority() == $two->getPriority()) {
                return 0;
            }

            return $one->getPriority() < $two->getPriority() ? 1 : -1;
        });
    }
}
