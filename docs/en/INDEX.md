Default Predict
===============================================


1. add the DefaultPredict extension.

2. you can exclude fields like this:

```php
private static $default_predict_exclude = [
    'Title',
];
```

3. you can include many rels like this:

```php
private static $default_predict_include_many_rels = [
    'OtherRel',
];
```

4. you can create your own prediction like this:

```php

protected function getDefaultPredictions() : array
{
    return [
        'MyField' => 'MyPrediction'
        'MyOtherField' => 'MyFooBarPrediction'
    ];
}

```
This data will be merge with the standard predictions.
