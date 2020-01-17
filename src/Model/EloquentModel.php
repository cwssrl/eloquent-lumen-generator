<?php

namespace Cws\EloquentModelGenerator\Model;

use Cws\CodeGenerator\Model\ClassModel;

/**
 * Class EloquentModel
 * @package Cws\EloquentModelGenerator\Model
 */
class EloquentModel extends ClassModel
{
    protected string $tableName;

    /**
     * @param string $tableName
     *
     * @return $this
     */
    public function setTableName($tableName)
    {
        $this->tableName = $tableName;

        return $this;
    }

    /**
     * @return string
     */
    public function getTableName()
    {
        return $this->tableName;
    }
}
