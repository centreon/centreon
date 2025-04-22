#!/usr/bin/env php
<?php

/**
 * Function to extract strings to translate from Dashboard widgets properties.json files
 */
function extractValues($array, $keys = ["title", "description", "label", "secondaryLabel"], &$results = []) {
    foreach ($array as $key => $value) {
        if (in_array($key, $keys) && is_string($value)) {
            $results[$value] = $value;
        } elseif (is_array($value)) {
            extractValues($value, $keys, $results);
        }
    }
    return $results;
}

if ($argc < 2) {
    echo "Usage: php {$argv[0]} <centreon-web directory>\n";
    exit(1);
}

if (is_dir($_SERVER['argv'][1])) {
    $centeonDir = $_SERVER['argv'][1];
} else {
    echo "{$argv[1]} if not a dir\n";
    echo "Usage: php {$argv[0]} <centreon-web directory>\n";
    exit(1);
}

$dir = $centeonDir . "/www/front_src/src/Dashboards/SingleInstancePage/Dashboard/Widgets";

if ( ! is_dir($dir)) {
    echo "{$argv[1]} if not a the centreon-web dir\n";
    echo "Usage: php {$argv[0]} <centreon-web directory>\n";
    exit(1);
}

$d = dir($dir);
$data = [];

while (false !== ($entry = $d->read())) {
    if ($entry == '.' || $entry == '..') {
        continue;
    }

    $entry = $dir.'/'.$entry;
    $propertiesJsonFile = $entry . '/properties.json';

    if (is_dir($entry) && is_file($propertiesJsonFile)) {
        $jsonData = @file_get_contents($propertiesJsonFile);

        if (empty($jsonData)) {
            continue;
        }
        $dataArray = json_decode($jsonData, true);
        $data = array_merge($data, extractValues($dataArray));

    }
}

$d->close();

if (count($data) > 0) {
    echo "<?php\n";
    foreach ($data as $key => $value) {
        if (!empty(trim($key))) {
            echo '_("' . trim($key) .'");' ."\n";
        }
    }
    echo "?>\n";
}

?>