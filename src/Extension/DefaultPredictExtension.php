<?php

namespace Sunnysideup\DefaultPredict\Extension;

use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;

/**
 * adds meta tag functionality to the Page_Controller or another DataObject.
 *
 * @property Site|DefaultPredictExtension $owner
 */
class DefaultPredictExtension extends DataExtension
{
    private static $base_default_predictor_limit = 5;

    private static $base_default_predictor_threshold = 0.5;

    /**
     * the greater the number, the less recency matters.
     * with a value of one, recency increases by 100% every step closer to the last record.
     * i.e the last record has five times more chance to the influencer
     * than the record that was created five steps ago.
     */
    private static $base_default_predictor_recency_factor = 0.5;

    private static $base_default_predict_exclude = [
        'ID',
        'Created',
        'LastEdited',
        'Version',
    ];

    private static $fields_that_must_be_equal = [
        'ClassName',
    ];

    public function populateDefaults()
    {
        $owner = $this->getOwner();
        $className = $owner->ClassName;

        // work out limit and treshold
        $limit = Config::inst()->get($className, 'default_predictor_limit') ?:
            Config::inst()->get(DefaultPredictExtension::class, 'base_default_predictor_limit');

        $threshold = Config::inst()->get($className, 'default_predictor_threshold') ?:
            Config::inst()->get(DefaultPredictExtension::class, 'base_default_predictor_threshold');

        $recencyFactor = Config::inst()->get($className, 'default_predictor_recency_factor') ?:
            Config::inst()->get(DefaultPredictExtension::class, 'base_default_predictor_recency_factor');

        $fieldsThatMustBeEqual = Config::inst()->get($className, 'fields_that_must_be_equal') ?:
            Config::inst()->get(DefaultPredictExtension::class, 'fields_that_must_be_equal');

        $predicts = $this->getDefaultPredictionPredictor($limit, $threshold, $recencyFactor, $fieldsThatMustBeEqual);

        // get class specific predictions
        if ($owner->hasMethod('getSpecificDefaultPredictions')) {
            $predicts = $predicts + $owner->getSpecificDefaultPredictions();
        }

        foreach ($predicts as $fieldName => $value) {
            $owner->{$fieldName} = $value;
        }
    }

    protected function getDefaultPredictionPredictor(int $limit, float $threshold, float $recencyFactor, array $fieldsThatMustBeEqual): array
    {
        $owner = $this->getOwner();
        $className = $owner->ClassName;

        // set return variable
        $predicts = [];

        // get field names
        $fieldNames = $this->getDefaultPredictionFieldNames($className);

        // must be equal
        $mustBeEqual = [];
        foreach($fieldsThatMustBeEqual as $fieldThatMustBeEqual) {
            if(isset($fieldNames[$fieldThatMustBeEqual])) {
                $mustBeEqual[$fieldThatMustBeEqual] = $owner->{$fieldThatMustBeEqual};
            }
        }
        // get last objects, based on limit;
        $objects = $className::get()
            ->sort(['ID' => 'DESC'])
            ->exclude(['ID' => $owner->ID])
            ->limit($limit)
        ;
        if(count($mustBeEqual)) {
            $objects = $objects->filter($mustBeEqual);
        }
        // print_r($objects->column('Purpose'));
        // print_r($objects->column('Title'));
        //store objects in memory
        $objectArray = [];
        foreach ($objects as $object) {
            $objectArray[] = $object;
        }
        // put the latest one last so that we can give it more weight.
        $objectArray = array_reverse($objectArray);

        // get the field names
        // print_r($fieldNames);
        // loop through fields
        foreach ($fieldNames as $fieldName) {
            $valueArray = [];
            // loop through objects
            $max = 0;
            foreach ($objectArray as $object) {
                $value = $object->{$fieldName};
                if (! $value) {
                    $value = '';
                }
                // give more weight to the last one used.
                for ($y = 0; $y <= $max; ++$y) {
                    $valueArray[] = $value;
                }
                $max += $recencyFactor;
            }
            // print_r($fieldName);
            // print_r($valueArray);
            if (count($valueArray)) {
                // work out if there is a value that comes back a lot, and, if so, add it to predicts
                $possibleValue = $this->defaultPredictionPredictorBestContender($valueArray, $threshold);
                if ($possibleValue) {
                    $predicts[$fieldName] = $possibleValue;
                }
            }
        }
        // print_r($predicts);
        return $predicts;
    }

    protected function getDefaultPredictionFieldNames(string $className): array
    {
        $excludeBase = (array) Config::inst()->get(DefaultPredictExtension::class, 'base_default_predict_exclude');
        $excludeMore = (array) Config::inst()->get($className, 'default_predict_exclude');
        $exclude = array_merge($excludeBase, $excludeMore);

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

    protected function defaultPredictionPredictorBestContender(array $valueArray, float $threshold)
    {
        $averages = $this->defaultPredictionPredictorCalculateAverages($valueArray);
        // sort by the most common one
        arsort($averages);
        foreach ($averages as $value => $percentage) {
            if ($percentage > $threshold) {
                return $value;
            }

            return null;
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
