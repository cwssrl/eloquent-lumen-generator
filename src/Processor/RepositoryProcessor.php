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
class RepositoryProcessor implements ProcessorInterface
{
    /**
     * @inheritdoc
     */
    public function process(EloquentModel $model, Config $config)
    {
        $this->createRepositoryForModelIfNeeded($config, $model);
        return $this;
    }

    /**
     * If repository key in config is not false creates the repository for current model
     *
     * @param Config $config
     * @param EloquentModel $model
     */
    private function createRepositoryForModelIfNeeded(Config $config, EloquentModel $model)
    {
        if ($config->get("repository") !== false) {
            $this->checkIfBaseFilesAlreadyExistsOtherwiseCreate($config, $model);
        }
    }

    private function checkIfBaseFilesAlreadyExistsOtherwiseCreate(Config $config, EloquentModel $model)
    {
        $repoResourceFolder = __DIR__ . '/../Resources/Repositories';
        $this->checkIfFileAlreadyExistsOrCopyIt(app_path('Exceptions'), "GenericException.php",
            $repoResourceFolder);
        $this->checkIfFileAlreadyExistsOrCopyIt(app_path('Repositories'), "RepositoryContract.php",
            $repoResourceFolder);
        $this->checkIfFileAlreadyExistsOrCopyIt(app_path('Repositories'), "EloquentRepository.php",
            $repoResourceFolder);
        dd("");
    }

    private function checkIfFileAlreadyExistsOrCopyIt($directoryWhereSearchFor,
                                                      $filenameToSearchFor, $directoryWhereGetFileToCopy)
    {
        if (!is_dir($directoryWhereSearchFor))
            mkdir($directoryWhereSearchFor);
        $filePath = $directoryWhereSearchFor . "/" . $filenameToSearchFor;
        if (!file_exists($filePath))
            copy($directoryWhereGetFileToCopy . "/" . $filenameToSearchFor, $filePath);
    }

    /**
     * @inheritdoc
     */
    public function getPriority()
    {
        return 2;
    }
}
