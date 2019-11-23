<?php

namespace App\Models;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class BaseModel extends Model
{
    protected $dateFormat = "Y-m-d H:i:s";

    /** @var array $relationsToDelete */
    public $relationsToDelete = [];

    /** @var array $blockingRelations */
    public $blockingRelations = [];

    /** @var array $secondaryDateFormat */
    private $secondaryDateFormat = ["Y-m-d\TH:i:s.u\Z", "Y-m-d H:i:s", "Y-m-d H:i:sZ", "Y-m-d\TH:i:sZ", "Y-m-d\TH:i:s"];

    protected function asDateTime($value)
    {
        // If this value is already a Carbon instance, we shall just return it as is.
        // This prevents us having to re-instantiate a Carbon instance when we know
        // it already is one, which wouldn't be fulfilled by the DateTime check.
        if ($value instanceof Carbon) {
            return $value;
        }

        // If the value is already a DateTime instance, we will just skip the rest of
        // these checks since they will be a waste of time, and hinder performance
        // when checking the field. We will just return the DateTime right away.
        if ($value instanceof DateTimeInterface) {
            return new Carbon(
                $value->format('Y-m-d H:i:s'), $value->getTimezone()
            );
        }

        // If this value is an integer, we will assume it is a UNIX timestamp's value
        // and format a Carbon object from this timestamp. This allows flexibility
        // when defining your date fields as they might be UNIX timestamps here.
        if (is_numeric($value)) {
            return Carbon::createFromTimestamp($value);
        }

        // If the value is in simply year, month, day format, we will instantiate the
        // Carbon instances from that format. Again, this provides for simple date
        // fields on the database, while still supporting Carbonized conversion.
        // fields on the database, while still supporting Carbonized conversion.
        if ($this->isStandardDateFormat($value)) {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        }

        // Finally, we will just assume this date is in the format used by default on
        // the database connection and use that format to create the Carbon object
        // that is returned back out to the developers after we convert it here.
        $format = str_replace('.v', '.u', $this->getDateFormat());
        if ($this->isValidDate($value, $format))
            return Carbon::createFromFormat(
                $format, $value
            );
        else
            foreach ($this->secondaryDateFormat as $format) {
                if ($this->isValidDate($value, $format))
                    return Carbon::createFromFormat(
                        $format, $value
                    );
            }
        return Carbon::createFromFormat(
            $format, $value
        );
    }

    /**
     * @param $relationName
     * @return bool
     */
    public function relationHasIdColumn($relationName)
    {
        return isset($this->manyToManyWithIdRelationNames) &&
            in_array($relationName, $this->manyToManyWithIdRelationNames);
    }

    public function getBlockingRelations()
    {
        if (isset($this->blockingRelations) && is_array($this->blockingRelations)) {
            foreach ($this->blockingRelations as $blockingRelations) {
                if ($this->$blockingRelations()->count())
                    return true;
            }
        }
        return null;
    }

    public function deleteRelations()
    {
        if (isset($this->relationsToDelete) && is_array($this->relationsToDelete)) {
            foreach ($this->relationsToDelete as $relationsToDelete) {
                if ($this->$relationsToDelete()->count())
                    $this->$relationsToDelete()->delete();
            }
        }
        return null;
    }

    public function delete()
    {
        $blockingRelations = $this->getBlockingRelations();
        if (!empty($blockingRelations))
            throw new \Exception(trans('exceptions.generic.delete_related_items'));
        \DB::beginTransaction();
        $this->deleteRelations();
        $output = parent::delete();
        \DB::commit();
        return $output;
    }


    /**
     * Check if $date as string is valid date
     *
     * @param string $date
     * @param string $format
     * @return bool True if valid date, otherwise false
     */
    private function isValidDate(string $date = null, string $format = "d/m/Y H:i:s")
    {
        if (is_null($date))
            return false;
        try {
            Carbon::createFromFormat($format, $date);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
