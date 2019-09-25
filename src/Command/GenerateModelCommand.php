<?php

namespace Cws\EloquentModelGenerator\Command;

use App\Http\Requests\ClassName\RequestStub;
use Astrotomic\Translatable\Translatable;
use Cws\EloquentModelGenerator\Config;
use Cws\EloquentModelGenerator\Generator;
use Cws\EloquentModelGenerator\Misc;
use Illuminate\Config\Repository as AppConfig;
use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class GenerateModelCommand
 * @package Cws\EloquentModelGenerator\Command
 */
class GenerateModelCommand extends Command
{
    /**
     * @var string
     */
    protected $name = 'cws:generate';

    /**
     * @var Generator
     */
    protected $generator;

    /**
     * @var AppConfig
     */
    protected $appConfig;

    /**
     * @var DatabaseManager
     */
    protected $databaseManager;

    /**
     * GenerateModelCommand constructor.
     * @param Generator $generator
     * @param AppConfig $appConfig
     */
    public function __construct(Generator $generator, AppConfig $appConfig, DatabaseManager $databaseManager)
    {
        parent::__construct();
        $this->generator = $generator;
        $this->appConfig = $appConfig;
        $this->databaseManager = $databaseManager;
    }

    /**
     * Add support for Laravel 5.5
     */
    public function handle()
    {
        $this->fire();
    }

    /**
     * Executes the command
     */
    public function fire()
    {
        $config = $this->createConfig();
        $schemaManager = $this->databaseManager->connection($config->get('connection'))->getDoctrineSchemaManager();
        $prefix = $this->databaseManager->connection($config->get('connection'))->getTablePrefix();
        //If argument is "all" we will create models for all tables
        if (strtolower($config->get('class_name')) === 'all') {
            $names = $schemaManager->listTableNames();
            //tables for which not create models by configuration
            $exceptTables = explode(",", strtolower($config->get('except-tables')));
            foreach ($names as $name) {
                //if table is from another schema and the one in connection it contains schema_name.table_name
                $isAnotherSchemaTableName = count(explode('.', $name)) > 1;
                if (!$isAnotherSchemaTableName && !in_array(strtolower($name), $exceptTables) && !$this->isTableNameARelationTableName($name, $names)) {
                    dump($name . " " . $this->getDefaultClassName($name));
                    $this->checkIfTableIsATranslationOneAndIfTranslatableIsInstalled($name);
                    $config->set("class_name", $this->getDefaultClassName($name));
                    $config->set("table_name", $name);
                    $model = $this->generator->generateModel($config, null, "output_path", null, true);
                    $this->output->writeln(sprintf('Model %s generated', $model->getName()->getName()));

                }
            }
        } else {
            $model = $this->generator->generateModel($config, null, "output_path", null, true);
            $this->output->writeln(sprintf('Model %s generated', $model->getName()->getName()));
        }
    }

    private function checkIfTableIsATranslationOneAndIfTranslatableIsInstalled($tableName)
    {
        if(Misc::endsWith($tableName,"_translations") && !class_exists("Astrotomic\Translatable\Locales",true))
        {
            $this->warn("Be careful, to manage translation tables you need to require Astrotomic/laravel-translatable");
        }
    }

    /**
     * @return Config
     */
    protected function createConfig()
    {
        $config = [];
        foreach ($this->getArguments() as $argument) {
            $config[$argument[0]] = $this->argument($argument[0]);
        }
        foreach ($this->getOptions() as $option) {
            $value = $this->option($option[0]);
            if ($option[2] == InputOption::VALUE_NONE && $value === false) {
                $value = null;
            }
            $config[$option[0]] = $value;
        }

        $config['db_types'] = $this->appConfig->get('eloquent_model_generator.db_types');
        if (array_key_exists('all', $config) && $config['all'] !== false) {
            $config = array_merge($config, array_fill_keys(["controller", "routes", "request", "repository"], true));
        }
        if (array_key_exists('all-api', $config) && $config['all-api'] !== false) {
            $config = array_merge($config, array_fill_keys(["api-controller", "api-routes", "request", "repository", "api-resource"], true));
        }
        return new Config($config, $this->appConfig->get('eloquent_model_generator.model_defaults'));
    }

    /**
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['class-name', InputArgument::REQUIRED, 'Model class name'],
        ];
    }

    /**
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['table-name', 'tn', InputOption::VALUE_OPTIONAL, 'Name of the table to use', null],
            ['output-path', 'op', InputOption::VALUE_OPTIONAL, 'Directory to store generated model', null],
            ['namespace', 'ns', InputOption::VALUE_OPTIONAL, 'Namespace of the model', null],
            ['base-class-name', 'bc', InputOption::VALUE_OPTIONAL, 'Model parent class', null],
            ['no-timestamps', 'ts', InputOption::VALUE_NONE, 'Set timestamps property to false', null],
            ['date-format', 'df', InputOption::VALUE_OPTIONAL, 'dateFormat property', null],
            ['connection', 'cn', InputOption::VALUE_OPTIONAL, 'Connection property', null],
            ['except-tables', 'et', InputOption::VALUE_OPTIONAL, 'Table to not process', null],
            ['controller', 'ct', InputOption::VALUE_OPTIONAL, 'If exists create controller too', false],
            ['controller-path', 'ctp', InputOption::VALUE_OPTIONAL, 'Path of controllers', null],
            ['routes-path', 'rtp', InputOption::VALUE_OPTIONAL, 'Routes path', null],
            ['routes', 'rt', InputOption::VALUE_OPTIONAL, 'Routes generation', false],
            ['request_namespace', 'rn', InputOption::VALUE_OPTIONAL, 'Request namespace', null],
            ['request', 'rqs', InputOption::VALUE_OPTIONAL, 'Request too', false],
            ['request-path', 'rqsp', InputOption::VALUE_OPTIONAL, 'Request path', null],
            ['api-resource', 'ar', InputOption::VALUE_OPTIONAL, 'Api resource too', false],
            ['repository', 're', InputOption::VALUE_OPTIONAL, 'Repository too', false],
            ['api-controller', 'ac', InputOption::VALUE_OPTIONAL, 'Api Controller too', false],
            ['api-routes', 'arou', InputOption::VALUE_OPTIONAL, 'Api Routes too', false],
            ['api-routes-path', 'arp', InputOption::VALUE_OPTIONAL, 'Api routes path', null],
            ['all', 'all', InputOption::VALUE_OPTIONAL, "If true creates all items", false],
            ['all-api', 'api', InputOption::VALUE_OPTIONAL, "If true creates all api items", false],
        ];
    }

    private function isTableNameARelationTableName($tableName, $allTablesName)
    {
        $singol = [];
        $containedInTableName = [];
        $singolarizedCurrentTable = Str::singular($tableName);
        foreach ($allTablesName as $p) {
            $sin = Str::singular($p);
            $singol[] = $sin;
            if (strpos($tableName, $sin) !== false && $sin !== $singolarizedCurrentTable)
                $containedInTableName[] = $sin;
        }

        $countContained = count($containedInTableName);
        if ($countContained < 2)
            return false;
        if ($countContained > 1 && (count(array_intersect($containedInTableName, $singol)) == $countContained)) {
            $first = explode("_", $containedInTableName[0]);
            $second = explode("_", $containedInTableName[1]);
            if (empty(array_intersect($first, $second)) && empty(array_intersect($second, $first)))
                return true;
        }
        return false;
    }

    private function getDefaultClassName($tableName)
    {
        return Str::ucfirst(Str::camel(Str::singular($tableName)));
    }
}