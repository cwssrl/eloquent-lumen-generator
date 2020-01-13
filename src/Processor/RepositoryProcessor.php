<?php

namespace Cws\EloquentModelGenerator\Processor;

use Cws\EloquentModelGenerator\Config;
use Cws\EloquentModelGenerator\Model\EloquentModel;
use Cws\EloquentModelGenerator\Misc;

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
        if (!Misc::endsWith($model->getTableName(), "_translations"))
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
            $config->checkIfFileAlreadyExistsOrCopyIt(
                $model,
                Misc::appPath('Repositories/' . $modelName),
                $modelName . "Contract.php",
                $repoResourceFolder,
                "ModelContract.php.stub"
            );
            $config->checkIfFileAlreadyExistsOrCopyIt(
                $model,
                Misc::appPath('Repositories/' . $modelName),
                "Eloquent" . $modelName . "Repository.php",
                $repoResourceFolder,
                "EloquentModelRepository.php.stub"
            );
            $contractPath = $config->getAppNamespace() . "Repositories\\$modelName\\" . $modelName . "Contract";
            $repoPath = $config->getAppNamespace() . "Repositories\\$modelName\\Eloquent" . $modelName . "Repository";
            $this->bindOnAppFile($contractPath, $repoPath);
        }
    }

    private function bindOnAppFile($contractName, $repoName)
    {
        $appPath = base_path("bootstrap/app.php");
        //\App\Repositories\Contracts\CommunityContentNewsContract
        //\App\Repositories\Traits\EloquentCommunityContentNewsRepository
        $stringToWrite = "\$app->bind($contractName::class,$repoName::class);";
        $content = file_get_contents($appPath);
        if (strpos($content, $stringToWrite) === false) {
            $content = str_replace('return $app;', "", $content);
            $content .= PHP_EOL . $stringToWrite;
            $content .= PHP_EOL . 'return $app;';
            file_put_contents($appPath, $content);
        }
    }

    private function checkIfBaseFilesAlreadyExistsOtherwiseCreate(Config $config, EloquentModel $model)
    {
        $repoResourceFolder = __DIR__ . '/../Resources/Repositories';
        $config->checkIfFileAlreadyExistsOrCopyIt(
            $model,
            Misc::appPath('Exceptions'),
            "GenericException.php",
            $repoResourceFolder,
            "GenericException.php.stub"
        );
        $config->checkIfFileAlreadyExistsOrCopyIt(
            $model,
            Misc::appPath('Repositories'),
            "RepositoryContract.php",
            $repoResourceFolder,
            "RepositoryContract.php.stub"
        );
        $config->checkIfFileAlreadyExistsOrCopyIt(
            $model,
            Misc::appPath('Repositories'),
            "EloquentRepository.php",
            $repoResourceFolder,
            "EloquentRepository.php.stub"
        );
    }

    /**
     * @inheritdoc
     */
    public function getPriority()
    {
        return 2;
    }
}
