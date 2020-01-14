<?php

namespace Cws\EloquentModelGenerator\Model;

/**
 * Class BelongsToMany
 * @package Cws\EloquentModelGenerator\Model
 */
class BelongsToMany extends Relation
{
    /**
     * @var string
     */
    protected $joinTable;

    /**
     * @var boolean
     */
    protected $withTimestamps;

    /**
     * @var array
     */
    protected $pivots;

    /**
     * BelongsToMany constructor.
     * @param string $tableName
     * @param string $joinTable
     * @param string $foreignColumnName
     * @param string $localColumnName
     * @param boolean $withTimestamps
     * @param array $pivots
     */
    public function __construct(
        $tableName,
        $joinTable,
        $foreignColumnName,
        $localColumnName,
        $withTimestamps,
        array $pivots = []
    ) {
        $this->joinTable = $joinTable;
        $this->withTimestamps = $withTimestamps;
        $this->pivots = $pivots;
        parent::__construct($tableName, $foreignColumnName, $localColumnName);
    }

    /**
     * @return string
     */
    public function getDefaultJoinTableName()
    {
        //return
    }

    /**
     * @return string
     */
    public function getJoinTable()
    {
        return $this->joinTable;
    }

    /**
     * @param string $joinTable
     *
     * @return $this
     */
    public function setJoinTable($joinTable)
    {
        $this->joinTable = $joinTable;

        return $this;
    }

    public function getWithTimestamps()
    {
        return $this->withTimestamps;
    }

    public function getPivotsAsString()
    {
        if (!count($this->pivots)) {
            return null;
        }

        $outputVal = "";
        foreach ($this->pivots as $pivot) {
            $outputVal .= ("'" . $pivot . "',");
        }

        return rtrim($outputVal, ",");
    }
}
