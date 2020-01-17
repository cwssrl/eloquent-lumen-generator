<?php

namespace Cws\EloquentModelGenerator;

use Cws\EloquentModelGenerator\Model\EloquentModel;
use Illuminate\Support\Str;

/**
 * Class Config
 * @package Cws\EloquentModelGenerator
 */
class Config
{
    protected array $config;

    /**
     * Config constructor.
     * @param array $inputConfig
     * @param array|null $appConfig
     */
    public function __construct($inputConfig, $appConfig = null)
    {
        $inputConfig = $this->resolveKeys($inputConfig);

        if ($appConfig !== null && is_array($appConfig)) {
            $inputConfig = $this->merge($inputConfig, $appConfig);
        }

        $this->config = $this->merge($inputConfig, $this->getBaseConfig());
    }

    /**
     * @param string $key
     * @param mixed|null $default
     * @return mixed|null
     */
    public function get($key, $default = null)
    {
        return $this->has($key) ? $this->config[$key] : $default;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has($key)
    {
        return isset($this->config[$key]);
    }

    /**
     * @param string $key
     * @param string $value
     * @return null
     */
    public function set($key, $value)
    {
        $this->config[$key] = $value;
    }

    /**
     * @param array $high
     * @param array $low
     * @return array
     */
    protected function merge(array $high, array $low)
    {
        foreach ($high as $key => $value) {
            if ($value !== null) {
                $low[$key] = $value;
            }
        }

        return $low;
    }

    /**
     * @param array $array
     * @return array
     */
    protected function resolveKeys(array $array)
    {
        $resolved = [];
        foreach ($array as $key => $value) {
            $resolvedKey = $this->resolveKey($key);
            $resolved[$resolvedKey] = $value;
        }

        return $resolved;
    }

    /**
     * @param string $key
     * @return mixed
     */
    protected function resolveKey($key)
    {
        return str_replace('-', '_', strtolower($key));
    }

    /**
     * @return array
     */
    protected function getBaseConfig()
    {
        return require((file_exists(base_path("config/eloquent_model_generator.php"))) ?
            base_path("config/eloquent_model_generator.php") : (__DIR__ . '/Resources/eloquent_model_generator.php'));
    }

    /**
     * Check if a file already exists otherwise create it by directives
     *
     * @param EloquentModel $model
     * @param $directoryWhereSearchFor
     * @param $filenameToSearchFor
     * @param $directoryWhereGetFileToCopy
     * @param $filenameToCopy
     * @param bool $overwrite
     */
    public function checkIfFileAlreadyExistsOrCopyIt(
        EloquentModel $model,
        $directoryWhereSearchFor,
        $filenameToSearchFor,
        $directoryWhereGetFileToCopy,
        $filenameToCopy,
        $overwrite = false
    ) {
        if (!is_dir($directoryWhereSearchFor)) {
            mkdir($directoryWhereSearchFor);
        }
        $filePath = $directoryWhereSearchFor . "/" . $filenameToSearchFor;
        if (!file_exists($filePath)) {
            copy($directoryWhereGetFileToCopy . "/" . $filenameToCopy, $filePath);
        } elseif ($overwrite) {
            unlink($filePath);
            copy($directoryWhereGetFileToCopy . "/" . $filenameToCopy, $filePath);
        }
        $content = file_get_contents($filePath);
        $modelName = $model->getName()->getName();
        $content = str_replace('$APP_NAME$', $this->getAppNamespace(), $content);
        $content = str_replace('$MODEL_NAME$', $modelName, $content);
        $content = str_replace('$CAMEL_MODEL_NAME$', Str::camel($modelName), $content);
        $content = str_replace(
            '$MODEL_FULL_CLASS$',
            $model->getNamespace()->getNamespace() . "\\" . $modelName,
            $content
        );
        $content = str_replace(
            '$PLURAL_SNAKE_MODEL_NAME$',
            Str::snake(Str::plural($modelName)),
            $content
        );
        $content = str_replace(
            '$PLURAL_PASCAL_MODEL_NAME$',
            Str::plural($modelName),
            $content
        );
        file_put_contents($filePath, $content);
    }

    /**
     * Get the app namespace
     *
     * @return mixed
     */
    public function getAppNamespace()
    {
        return \Illuminate\Container\Container::getInstance()->getNamespace();
    }
}
