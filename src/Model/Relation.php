<?php

namespace Cws\EloquentModelGenerator\Model;

/**
 * Class Relation
 * @package Cws\EloquentModelGenerator\Model
 */
abstract class Relation
{
    protected string $tableName;

    protected string $foreignColumnName;

    protected string $localColumnName;

    /**
     * Relation constructor.
     * @param string $tableName
     * @param string $joinColumnName
     * @param string $localColumnName
     */
    public function __construct(string $tableName, string $joinColumnName, string $localColumnName)
    {
        $this->setTableName($tableName);
        $this->setForeignColumnName($joinColumnName);
        $this->setLocalColumnName($localColumnName);
    }

    /**
     * @return string
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }

    /**
     * @param string $tableName
     *
     * @return $this
     */
    public function setTableName(string $tableName): Relation
    {
        $this->tableName = $tableName;

        return $this;
    }

    /**
     * @return string
     */
    public function getForeignColumnName(): string
    {
        return $this->foreignColumnName;
    }

    /**
     * @param string $foreignColumnName
     *
     * @return $this
     */
    public function setForeignColumnName(string $foreignColumnName): Relation
    {
        $this->foreignColumnName = $foreignColumnName;

        return $this;
    }

    /**
     * @return string
     */
    public function getLocalColumnName(): string
    {
        return $this->localColumnName;
    }

    /**
     * @param string $localColumnName
     *
     * @return $this
     */
    public function setLocalColumnName(string $localColumnName): Relation
    {
        $this->localColumnName = $localColumnName;

        return $this;
    }
}
