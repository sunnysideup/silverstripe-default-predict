<?php

namespace Sunnysideup\DefaultPredict\Extension;

use SilverStripe\Core\Extension;
use SilverStripe\ORM\FieldType\DBHTMLText;

/**
 * adds meta tag functionality to the Page_Controller.
 */
class DefaultPredictExtension extends DataExtension
{

    protected function DefaultPredictionPredictor($limit = 5, $percentageTreshold = 0.5)
    {
        $className = get_class($this->getOwner());
        if ($className) {
            $predicts = [];
            $fieldsDb = array_keys(Config::inst()->get($className, 'db'));
            $fieldsHasOne = array_keys(Config::inst()->get($className, 'has_one'));
            array_walk($fieldsHasOne, function (&$value, $key) {
                $value .= 'ID';
            });
            $objects = $className::get()
                ->sort(['ID' => 'DESC'])
                ->exclude(['ID' => $this->ID])
                ->limit(5);
            $objectArray = [];
            foreach ($objects as $object) {
                $objectArray[] = $object;
            }
            $fieldNames = array_merge($fieldsDb, $fieldsHasOne);
            $minusLimit = $limit * -1;
            foreach ($fieldNames as $fieldName) {
                $valueArray = [];
                foreach ($objectArray as $pos => $object) {
                    $times = ($minusLimit + $pos) * -1;
                    for ($y = 0; $y < $times; $y++) {
                        $valueArray[] = $object->$fieldName;
                    }
                }
                $value = $this->defaultPredictionPredictorBestContendor($valueArray, $percentageTreshold);
                if ($value !== null) {
                    $predicts[$fieldName] = $value;
                }
            }
        }
        return $predicts;
    }



    protected function defaultPredictionPredictorBestContendor(array $valueArray, float $percentageTreshold)
    {
        $averages = $this->defaultPredictionPredictorCalculateAverages($valueArray);
        // sort by the most commont one
        arsort($averages);
        foreach ($averages as $value => $percentage) {
            if ($percentage > $percentageTreshold) {
                return $value;
            } else {
                return null;
            }
        }
        return null;
    }

    protected function defaultPredictionPredictorCalculateAverages(array $array)
    {
        $num = count($array);
        return array_map(
            function ($val) use ($num, $round) {
                return $val / $num;
            },
            array_count_values($array)
        );
    }
}
