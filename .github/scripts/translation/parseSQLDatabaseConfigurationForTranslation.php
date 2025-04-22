#!/usr/bin/env php
<?php

# $PHP $BASE_DIR/pareInsertTopologyForTranslation.php > $BASE_DIR_PROJECT/www/install/menu_translation.php

if ($argc < 2) {
    echo "Usage: php {$argv[0]} <SQL file to analyse>\n";
    exit(1);
}

if (is_file($_SERVER['argv'][1])) {
    $file = $_SERVER['argv'][1];
} else {
    echo "{$argv[1]} if not a file\n";
    echo "Usage: php {$argv[0]} <SQL file to analyse>\n";
    exit(1);
}

$content = @file($file);

if (empty($content)) {
    echo "File $file is empty\n";
    exit(1);
}

$data = [];
if (strcmp(basename($file), "insertBaseConf.sql") == 0) {
    $startBrokerAnalisys = false;
    $brokerPattern1 = "/INSERT INTO `cb_field` \(`cb_field_id`, `fieldname`, `displayname`, `description`, `fieldtype`, `external`\) VALUES/";
    $brokerPattern2 = "/INSERT INTO `cb_field` \(`cb_field_id`, `fieldname`, `displayname`, `description`, `fieldtype`, `external`, `cb_fieldgroup_id`\) VALUES/";
    $brokerPattern3 = "/INSERT INTO `cb_fieldgroup` \(`cb_fieldgroup_id`, `groupname`, `displayname`, `multiple`, `group_parent_id`\) VALUES/";

    foreach ($content as $line) {
        if (empty($line) || preg_match('/^\s/', $line)) {
            $startBrokerAnalisys = false;
        }

        if ($startBrokerAnalisys) {
            $values = explode(',', $line);
            $data[$values[2]] = $values[2];
        }

        if (
            preg_match($brokerPattern1 , $line) || preg_match($brokerPattern2, $line) || preg_match($brokerPattern3, $line)
        ){
            $startBrokerAnalisys = true;
        }
    }
} elseif (strcmp(basename($file), "insertTopology.sql") == 0) {
    $topologyPattern1 = "/INSERT INTO `topology` \(.*\) VALUES/";
    $topologyPattern2 = "/\(NULL.*\)/";

    foreach ($content as $line) {
        if (preg_match($topologyPattern1, $line))
        {
            $aSqlValues = explode('VALUES', $line);
            # Removing parentheses
            $aSqlValues[1] = substr($aSqlValues[1], strpos($aSqlValues[1], '(') + 1, strpos($aSqlValues[1], ')'));
            # Removing spaces before and after
            $aSqlValues[1] = trim($aSqlValues[1]);
            $aValues = explode(',', $aSqlValues[1]);
            # If array is not empty
            if (count($aValues)) {
                if (
                    preg_match('/NULL/', trim($aValues[0]))
                    || preg_match('/\d+/', trim($aValues[0]))
                ) {
                    $data[$aValues[1]] = $aValues[1];
                } else {
                    $data[$aValues[0]] = $aValues[0];
                }
            }
        } elseif (preg_match($topologyPattern2, $line))
        {
            $aValues = explode(',', $line);
            if (count($aValues)) {
                if (
                    preg_match('/NULL/', trim($aValues[0]))
                    || preg_match('/\d+/', trim($aValues[0]))
                )  {
                    $data[$aValues[1]] = $aValues[1];
                } else {
                    $data[$aValues[0]] = $aValues[0];
                }
            }
        }
    }
} elseif (strcmp(basename($file), "install.sql") == 0) {
    $topologyPattern = "/INSERT INTO `topology` \(.*\) VALUES/";
    $startBrokerAnalisys = false;

    foreach ($content as $line) {
        if (empty($line) || preg_match('/^\s/', $line)) {
            $startBrokerAnalisys = false;
        }

        if ($startBrokerAnalisys) {
            $line = substr($line, strpos($line, '(') + 1, strpos($line, ')'));
            # Removing spaces before and after
            $line = trim($line);
            $aValues = explode(',', $line);
            if (count($aValues)) {
                if (
                    preg_match('/NULL/', trim($aValues[0]))
                    || preg_match('/\d+/', trim($aValues[0]))
                )  {
                    $data[$aValues[1]] = $aValues[1];
                } else {
                    $data[$aValues[0]] = $aValues[0];
                }
            }
        }

        if (preg_match($topologyPattern , $line)){
            $startBrokerAnalisys = true;
        }
    }
}

if (count($data) > 0) {
    echo "<?php\n";
    foreach ($data as $key => $value) {
        if (!empty(trim($key))) {
            echo '_(' . trim($key) .');' ."\n";
        }
    }
    echo "?>\n";
}

?>

