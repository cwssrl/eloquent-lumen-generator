<?php

namespace Cws\EloquentModelGenerator\Model;

/**
 * Class BelongsToMany
 * @package Cws\EloquentModelGenerator\Model
 */
class BelongsToMany extends Relation
{
    protected string $joinTable;

    protected bool $withTimestamps;

    protected array $pivots;

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
        string $tableName,
        string $joinTable,
        string $foreignColumnName,
        string $localColumnName,
        string $withTimestamps,
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
    public function getJoinTable(): string
    {
        return $this->joinTable;
    }

    /**
     * @param string $joinTable
     *
     * @return $this
     */
    public function setJoinTable(string $joinTable): BelongsToMany
    {
        $this->joinTable = $joinTable;

        return $this;
    }

    /**
     * @return bool
     */
    public function getWithTimestamps(): string
    {
        return $this->withTimestamps;
    }

    /**
     * @return string|null
     */
    public function getPivotsAsString(): string
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
