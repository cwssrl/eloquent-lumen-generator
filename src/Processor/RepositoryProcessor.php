<?php

namespace Cws\EloquentModelGenerator\Processor;

use Cws\CodeGenerator\Model\ClassNameModel;
use Cws\CodeGenerator\Model\DocBlockModel;
use Cws\CodeGenerator\Model\MethodModel;
use Cws\CodeGenerator\Model\NamespaceModel;
use Cws\CodeGenerator\Model\UseClassModel;
use Cws\EloquentModelGenerator\Config;
use Cws\EloquentModelGenerator\Model\EloquentModel;
use Illuminate\Support\Str;

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
            $repoResourceFolder = __DIR__ . '/../Resources/Repositories';
            $modelName = $model->getName()->getName();
            $this->checkIfFileAlreadyExistsOrCopyIt($model, app_path('Repositories/' . $modelName),
                $modelName . "Contract.php",
                $repoResourceFolder, "ModelContract.php");
            $this->checkIfFileAlreadyExistsOrCopyIt($model, app_path('Repositories/' . $modelName),
                "Eloquent" . $modelName . "Repository.php",
                $repoResourceFolder, "EloquentModelRepository.php");
        }
    }

    private function checkIfBaseFilesAlreadyExistsOtherwiseCreate(Config $config, EloquentModel $model)
    {
        $repoResourceFolder = __DIR__ . '/../Resources/Repositories';
        $this->checkIfFileAlreadyExistsOrCopyIt($model, app_path('Exceptions'), "GenericException.php",
            $repoResourceFolder, "GenericException.php");
        $this->checkIfFileAlreadyExistsOrCopyIt($model, app_path('Repositories'), "RepositoryContract.php",
            $repoResourceFolder, "RepositoryContract.php");
        $this->checkIfFileAlreadyExistsOrCopyIt($model, app_path('Repositories'), "EloquentRepository.php",
            $repoResourceFolder, "EloquentRepository.php");
    }

    private function checkIfFileAlreadyExistsOrCopyIt(EloquentModel $model, $directoryWhereSearchFor,
                                                      $filenameToSearchFor,
                                                      $directoryWhereGetFileToCopy,
                                                      $filenameToCopy
    )
    {
        if (!is_dir($directoryWhereSearchFor))
            mkdir($directoryWhereSearchFor);
        $filePath = $directoryWhereSearchFor . "/" . $filenameToSearchFor;
        if (!file_exists($filePath)) {
            copy($directoryWhereGetFileToCopy . "/" . $filenameToCopy, $filePath);
        }
        $content = file_get_contents($filePath);
        $content = str_replace('$APP_NAME$', $this->getAppNamespace(), $content);
        $content = str_replace('$MODEL_NAME$', $model->getName()->getName(), $content);
        $content = str_replace('$MODEL_FULL_CLASS$',
            $model->getNamespace()->getNamespace() . "\\" . $model->getName()->getName(),
            $content);
        file_put_contents($filePath, $content);

    }

    private function getAppNamespace()
    {
        return \Illuminate\Container\Container::getInstance()->getNamespace();
    }

    /**
     * @inheritdoc
     */
    public function getPriority()
    {
        return 2;
    }
}
