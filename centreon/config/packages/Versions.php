<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Finder\Finder;

return static function (ContainerConfigurator $container): void {
    $finder = new Finder();
    $dotenv = new Dotenv();

    $apiLatestVersion = null;

    $versionFiles = $finder
        ->files()
        ->ignoreDotFiles(false)
        ->name('.*')
        ->in(__DIR__ . '/../../config/versions');

    $versions = [];
    foreach ($versionFiles as $versionFile) {
        $content = $dotenv->parse($versionFile->getContents(), $versionFile->getPath());
        if (array_key_exists('VERSION', $content) && preg_match('/^(\d+\.\d+)\.\d+$/', $content['VERSION'], $matches)) {
            $moduleName = ltrim($versionFile->getBasename(), '.');
            $versions[$moduleName] = $content['VERSION'];
            if ($moduleName === 'core') {
                $apiLatestVersion = $matches[1];
            }
        }
    }

    $container->parameters()->set('api.version.latest', $apiLatestVersion);
    $container->parameters()->set('versions', $versions);
};
