<?php

namespace Cws\EloquentModelGenerator\Command;

use Illuminate\Config\Repository as AppConfig;
use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Cws\EloquentModelGenerator\Config;
use Cws\EloquentModelGenerator\Generator;
use Cws\EloquentModelGenerator\TypeRegistry;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Support\Str;

/**
 * Class GenerateModelCommand
 * @package Cws\EloquentModelGenerator\Command
 */
class GenerateModelCommand extends Command
{
    /**
     * @var string
     */
    protected $name = 'cws:generate:model';

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
                if (!$isAnotherSchemaTableName && !in_array(strtolower($name), $exceptTables) && !$this->isTableNameARelationTableName($name, $names) && !ends_with($name, "_translations")) {
                    $config->set("class_name", $this->getDefaultClassName($name));
                    $model = $this->generator->generateModel($config);
                    $this->output->writeln(sprintf('Model %s generated', $model->getName()->getName()));
                }
            }
        } else {
            $model = $this->generator->generateModel($config);
            $this->output->writeln(sprintf('Model %s generated', $model->getName()->getName()));
        }
    }

    /**
     * Add support for Laravel 5.5
     */
    public function handle()
    {
        $this->fire();
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
            ['except-tables', 'et', InputOption::VALUE_OPTIONAL, 'Table to not process', null]
        ];
    }

    private function getDefaultClassName($tableName)
    {
        return Str::ucfirst(Str::camel(Str::singular($tableName)));
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
        if ($countContained > 1 && (count(array_intersect($containedInTableName, $singol)) == $countContained))
            return true;
        return false;
    }
}
