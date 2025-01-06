#!/usr/bin/env php
<?php

function help()
{
    echo "install-centreon-module.php -b <bootstrap_file> -m <module_name> [-h]\n\n";
}

$options = getopt("b:m:hu");

if (isset($options['h']) && false === $options['h']) {
    help();
    exit(0);
}

if (false === isset($options['b']) || false === isset($options['m'])) {
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

if (false == is_dir(_CENTREON_PATH_ . '/www/modules/' . $options['m'])) {
  echo "The module directory is not installed on filesystem.\n";
  exit(1);
}

$utilsFactory = new \CentreonLegacy\Core\Utils\Factory($dependencyInjector);
$utils = $utilsFactory->newUtils();

$factory = new \CentreonLegacy\Core\Module\Factory($dependencyInjector, $utils);
$information = $factory->newInformation();

$installedInformation = $information->getInstalledInformation($options['m']);
if (!isset($options['u']) && $installedInformation) {
    echo "The module is already installed in database.\n";
    exit(1);
} elseif (isset($options['u']) && false === $options['u'] && !$installedInformation) {
    echo "The module is not installed in database.\n";
    exit(1);
}

if (isset($options['u']) && false === $options['u']) {
    $upgrader = $factory->newUpgrader($options['m'], $installedInformation['id']);
    $upgrader->upgrade();
} else {
    $installer = $factory->newInstaller($options['m']);
    $installer->install();
}