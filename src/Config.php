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
    /**
     * @var array
     */
    protected $config;

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
            base_path("config/eloquent_model_generator.php") :
            (__DIR__ . '/Resources/config.php'));

    }

    public function checkIfFileAlreadyExistsOrCopyIt(EloquentModel $model, $directoryWhereSearchFor,
                                                     $filenameToSearchFor,
                                                     $directoryWhereGetFileToCopy,
                                                     $filenameToCopy, $overwrite = false
    )
    {
        if (!is_dir($directoryWhereSearchFor))
            mkdir($directoryWhereSearchFor);
        $filePath = $directoryWhereSearchFor . "/" . $filenameToSearchFor;
        if (!file_exists($filePath)) {
            copy($directoryWhereGetFileToCopy . "/" . $filenameToCopy, $filePath);
        } else if ($overwrite) {
            unlink($filePath);
            copy($directoryWhereGetFileToCopy . "/" . $filenameToCopy, $filePath);
        }
        $content = file_get_contents($filePath);
        $content = str_replace('$APP_NAME$', $this->getAppNamespace(), $content);
        $content = str_replace('$MODEL_NAME$', $model->getName()->getName(), $content);
        $content = str_replace('$CAMEL_MODEL_NAME$', Str::camel($model->getName()->getName()), $content);
        $content = str_replace('$MODEL_FULL_CLASS$',
            $model->getNamespace()->getNamespace() . "\\" . $model->getName()->getName(),
            $content);
        file_put_contents($filePath, $content);

    }

    private function getAppNamespace()
    {
        return \Illuminate\Container\Container::getInstance()->getNamespace();
    }
}
