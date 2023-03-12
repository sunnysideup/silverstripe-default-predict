<?php

namespace Sunnysideup\DefaultPredict\Extension;

use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;

/**
 * adds meta tag functionality to the Page_Controller.
 */
class DefaultPredictExtension extends DataExtension
{

    public function populateDefaults()
    {
        // get basics
        $owner = $this->getOwner();
        $className = $owner->ClassName;

        // work out limit and treshold
        $limit = Config::inst()->get($className, 'default_predictor_limit') ?: 5;
        $threshold = Config::inst()->get($className, 'default_predictor_threshold') ?: 0.5;
        $recencyFactor = Config::inst()->get($className, 'default_predictor_recency_factor') ?: 1;

        // get predictions
        $predicts = $this->getDefaultPredictionPredictor($limit, $threshold, $recencyFactor);

        // get class specific predictions
        if ($owner->hasMethod('getSpecificDefaultPredictions')) {
            $predicts = $predicts + $owner->getSpecificDefaultPredictions();
        }

        // set values
        foreach ($predicts as $fieldName => $value) {
            $this->$fieldName = $value;
        }
    }

    protected function getDefaultPredictionPredictor(int $limit, float $threshold, float $recencyFactor): array
    {
        // get basics
        $owner = $this->getOwner();
        $className = $owner->ClassName;

        // set return variable
        $predicts = [];

        // get last objects, based on limit;
        $objects = $className::get()
            ->sort(['ID' => 'DESC'])
            ->exclude(['ID' => $owner->ID])
            ->limit($limit);

        //store objects in memory
        $objectArray = [];
        foreach ($objects as $object) {
            $objectArray[] = $object;
        }
        // put the latest one last so that we can give it more weight.
        $objects = array_reverse($objects);

        // get the field names
        $fieldNames = $this->getDefaultPredictionFieldNames($className);

        // loop through fields
        foreach ($fieldNames as $fieldName) {
            $valueArray = [];

            // loop through objects
            foreach ($objectArray as $pos => $object) {
                // ignore empty ones
                if ($object->$fieldName) {
                    // give more weight to the last one used.
                    for ($y = 0; $y <= $pos; $y += $recencyFactor) {
                        $valueArray[] = $object->$fieldName;
                    }
                }
            }
            // work out if there is a value that comes back a lot, and, if so, add it to predicts
            $possibleValue = $this->defaultPredictionPredictorBestContendor($valueArray, $threshold);
            if ($possibleValue !== null) {
                $predicts[$fieldName] = $possibleValue;
            }
        }
        return $predicts;
    }


    protected function getDefaultPredictionFieldNames(string $className): array
    {
        // get exclusions
        $exclude = Config::inst()->get($className, 'default_predict_exclude');
        if (!is_array($exclude)) {
            $exclude = [];
        }

        // get db and has_one fields
        $fieldsDb = array_keys(Config::inst()->get($className, 'db'));
        $fieldsHasOne = array_keys(Config::inst()->get($className, 'has_one'));

        // add ID part to has_one fields
        array_walk($fieldsHasOne, function (&$value, $key) {
            $value .= 'ID';
        });

        // return merge of arrays minus the exclude ones.
        return array_diff(
            array_merge($fieldsDb, $fieldsHasOne),
            $exclude
        );
    }

    protected function defaultPredictionPredictorBestContendor(array $valueArray, float $threshold)
    {
        $averages = $this->defaultPredictionPredictorCalculateAverages($valueArray);
        // sort by the most common one
        arsort($averages);
        foreach ($averages as $value => $percentage) {
            if ($percentage > $threshold) {
                return $value;
            } else {
                return null;
            }
        }
        return null;
    }

    protected function defaultPredictionPredictorCalculateAverages(array $array)
    {
        $num = count($array); // provides the value for num
        return array_map(
            function ($val) use ($num) {
                return $val / $num;
            },
            array_count_values($array) // provides the value for $val
        );
    }
}
