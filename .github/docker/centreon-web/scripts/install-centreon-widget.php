#!/usr/bin/env php
<?php

function help()
{
  echo "install-centreon-widget.php -b <bootstrap_file> -w <widget_name> [-h]\n\n";
}

$options = getopt("b:w:hu");

if (isset($options['h']) && false === $options['h']) {
    help();
    exit(0);
}

if (false === isset($options['b']) || false === isset($options['w'])) {
    echo "Missing arguments.\n";
    exit(1);
}

if (false === file_exists($options['b'])) {
    echo "The configuration doesn't exist.\n";
    exit(1);
}

require_once $options['b'];

if (!defined('_CENTREON_PATH_')) {
    echo "Centreon configuration not loaded.\n";
    exit(1);
}

if (false == is_dir(_CENTREON_PATH_ . '/www/widgets/' . $options['w'])) {
  echo "The widget directory is not installed on filesystem.\n";
  exit(1);
}

$utilsFactory = new \CentreonLegacy\Core\Utils\Factory($dependencyInjector);
$utils = $utilsFactory->newUtils();

$factory = new \CentreonLegacy\Core\Widget\Factory($dependencyInjector, $utils);
$information = $factory->newInformation();
$isInstalled = $information->isInstalled($options['w']);

if (!isset($options['u']) && $isInstalled) {
    echo "The widget is already installed in database.\n";
    exit(1);
} elseif (isset($options['u']) && false === $options['u'] && !$isInstalled) {
    echo "The widget is not installed in database.\n";
    exit(1);
}

if (isset($options['u']) && false === $options['u']) {
    $upgrader = $factory->newUpgrader($options['w']);
    $upgrader->upgrade();
} else {
    $installer = $factory->newInstaller($options['w']);
    $installer->install();
}
