<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

namespace Centreon\Domain\Service;

use CentreonLegacy\ServiceProvider;
use Psr\Container\ContainerInterface;

/**
 * Class to manage external frontend components provided by modules and widgets
 */
class FrontendComponentService
{
    /**
     * List of class dependencies
     *
     * @return array
     */
    public static function dependencies(): array
    {
        return [
            ServiceProvider::CENTREON_LEGACY_MODULE_INFORMATION,
        ];
    }

    /**
     * FrontendComponentService constructor
     *
     * @param ContainerInterface $services
     */
    public function __construct(private ContainerInterface $services)
    {
    }

    /**
     * Get directory files grouped by directory matching regex
     *
     * @param string $dir the directory to explore
     * @param array $results the found files
     * @param string $regex the regex to match
     * @return array
     */
    private function getDirContents(
        string $dir,
        array &$results = [],
        string $regex = '/.*/',
        bool $recursive = true
    ): array {
        $files = [];
        if (is_dir($dir)) {
            $files = scandir($dir);
        }

        foreach ($files as $key => $value) {
            $path = $dir . DIRECTORY_SEPARATOR . $value;
            if (! is_dir($path) && preg_match($regex, $path)) {
                // group files by directory
                $results[dirname($path)][] = basename($path);
            } elseif ($recursive && $value != '.' && $value != '..') {
                $this->getDirContents($path, $results, $regex);
            }
        }

        return $results;
    }

    /**
     * Get list of installed modules
     *
     * @return array list of installed modules
     */
    private function getInstalledModules(): array
    {
        return $this->services->get(ServiceProvider::CENTREON_LEGACY_MODULE_INFORMATION)
            ->getInstalledList();
    }

    /**
     * Get list of chunks found in module directory
     * Chunks represent common source code between hooks and pages
     *
     * @return array the list of chunks
     */
    private function getChunksByModuleName(string $moduleName): array
    {
        $chunks = [];
        $modulePath = __DIR__ . '/../../../../www/modules/' . $moduleName . '/static';

        $files = [];
        $this->getDirContents($modulePath, $files, '/\.js$/', false);
        foreach ($files as $path => $chunkFiles) {
            $chunkPath = str_replace(__DIR__ . '/../../../../www', '', $path);
            foreach ($chunkFiles as $chunkFile) {
                $chunks[] = $chunkPath . '/' . $chunkFile;
            }
        }

        return $chunks;
    }

    /**
     * Get structure of files which compose external pages or hooks
     *
     * @param string $path The absolute base path of the external bundle
     * @param array $files The files of the bundle (js and css)
     * @param array $commons The common chunks between bundles
     * @return array The structure of the files needed to load an external bundle
     */
    private function getBundleStructure(string $path, array $files, array $commons): array
    {
        // set relative path
        $relativePath = str_replace(__DIR__ . '/../../../../www', '', $path);

        // add page parameters (js and css files)
        $structure = [
            'js' => [
                'commons' => $commons,
                'chunks' => [],
                'bundle' => null,
            ],
            'css' => [],
        ];

        foreach ($files as $file) {
            if (preg_match('/\.js$/', $file)) {
                if (preg_match('/^(index|main)/', $file)) {
                    $structure['js']['bundle'] = $relativePath . '/' . $file;
                } else {
                    $structure['js']['chunks'][] = $relativePath . '/' . $file;
                }
            } elseif (preg_match('/\.css$/', $file)) {
                $structure['css'][] = $relativePath . '/' . $file;
            }
        }

        return $structure;
    }

    /**
     * Get frontend external hooks
     *
     * @return array The list of hooks (js and css)
     */
    public function getHooks(): array
    {
        $installedModules = $this->getInstalledModules();

        // search in each installed modules if there are hooks
        $hooks = [];
        foreach (array_keys($installedModules) as $installedModule) {
            $modulePath = __DIR__ . '/../../../../www/modules/' . $installedModule . '/static/hooks';
            $chunks = $this->getChunksByModuleName($installedModule);
            $files = [];
            $this->getDirContents($modulePath, $files, '/\.(js|css)$/');
            foreach ($files as $path => $hookFiles) {
                if (preg_match('/\/static\/hooks(\/.+)$/', $path, $hookMatches)) {
                    // parse hook name by removing beginning of the path
                    $hookName = $hookMatches[1];

                    $hookParameters = $this->getBundleStructure($path, $hookFiles, $chunks);

                    if (! $hookParameters['js']['bundle'] !== null) {
                        $hooks[$hookName] = $hookParameters;
                    }
                }
            }
        }

        return $hooks;
    }

    /**
     * Get frontend external pages
     *
     * @return array The list of pages (routes, js and css)
     */
    public function getPages(): array
    {
        $installedModules = $this->getInstalledModules();

        // search in each installed modules if there are pages
        $pages = [];
        foreach (array_keys($installedModules) as $installedModule) {
            $modulePath = __DIR__ . '/../../../../www/modules/' . $installedModule . '/static/pages';
            $chunks = $this->getChunksByModuleName($installedModule);
            $files = [];
            $this->getDirContents($modulePath, $files, '/\.(js|css)$/');
            foreach ($files as $path => $pageFiles) {
                if (preg_match('/\/static\/pages(\/.+)$/', $path, $pageMatches)) {
                    $pageParameters = $this->getBundleStructure($path, $pageFiles, $chunks);

                    if ($pageParameters['js']['bundle'] !== null) {
                        // parse page name by removing beginning of the path
                        $pageName = str_replace('/_', '/:', $pageMatches[1]);

                        $pages[$pageName] = $pageParameters;
                    }
                }
            }
        }

        return $pages;
    }
}
