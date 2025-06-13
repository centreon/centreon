#!/usr/bin/env php
<?php

// extensions of ReactJS files, used when going through a directory
$extensions = ['ts', 'tsx'];

// rips gettext strings from $file and prints them in C format
function do_file($file)
{
    $content = @file_get_contents($file);

    if (empty($content)) {
        return;
    }

    preg_match_all("/export\sconst\slabel.*\s=\n?\s+(?:'|\"|`)(.+)(?:'|\"|`);/", $content, $matches);

    if (count($matches[0]) > 0) {
        echo "/* $file */\n";
    }
    for ($i=0; $i < count($matches[0]); $i++) {
        echo '_("' . trim($matches[1][$i],'\'"') .'");' . "\n";
    }
}

// go through a directory
function do_dir($dir)
{
    $d = dir($dir);
    while (false !== ($entry = $d->read())) {
        if ($entry == '.' || $entry == '..') {
            continue;
        }

        $entry = $dir . '/' . $entry;

        if (is_dir($entry)) { // if a directory, go through it
            do_dir($entry);
        } else { // if file, parse only if extension is matched
            $pi = pathinfo($entry);

            if (isset($pi['extension']) && in_array($pi['extension'], $GLOBALS['extensions'])) {
                do_file($entry);
            }
        }
    }

    $d->close();
}
echo "<?php\n";
for ($ac=1; $ac < $_SERVER['argc']; $ac++) {
    if (is_dir($_SERVER['argv'][$ac])) { // go through directory
        do_dir($_SERVER['argv'][$ac]);
    } else { // do file
        do_file($_SERVER['argv'][$ac]);
    }
}

if ($argc < 2) {
    echo "Usage: {$argv[0]} CENTREON_WWW/ > output.php\n";
}
