<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace CentreonLegacy\Core\Configuration;

use CentreonModule\Infrastructure\Source\ModuleSource;
use CentreonModule\Infrastructure\Source\WidgetSource;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * Service provide configuration data.
 */
class Configuration
{
    public const CENTREON_PATH = 'centreon_path';

    /** @var array the global configuration */
    protected $configuration;

    /** @var string the centreon path */
    protected $centreonPath;

    /** @var Finder */
    protected $finder;

    /**
     * @param array $configuration the global configuration (mainly database)
     * @param string $centreonPath the centreon directory path
     * @param Finder $finder
     */
    public function __construct(array $configuration, string $centreonPath, Finder $finder)
    {
        $this->configuration = $configuration;
        $this->centreonPath = $centreonPath;
        $this->finder = $finder;
    }

    /**
     * Get configuration parameter by key.
     *
     * @param string $key the key parameter to get
     *
     * @return string the parameter value
     */
    public function get(string $key)
    {
        $value = null;

        // specific case for centreon path which is not stored in $conf_centreon
        if ($key === static::CENTREON_PATH) {
            $value = $this->centreonPath;
        } elseif (isset($this->configuration[$key])) {
            $value = $this->configuration[$key];
        }

        return $value;
    }

    public function getFinder() : ?Finder
    {
        return $this->finder;
    }

    public function getModulePath() : string
    {
        return $this->centreonPath . ModuleSource::PATH;
    }

    public function getWidgetPath() : string
    {
        return $this->centreonPath . WidgetSource::PATH;
    }

    /**
     * Locate all yml files in src/ModuleFolder/config/ and parse them to array.
     *
     * @param string $moduleFolder
     *
     * @return array
     */
    public function getModuleConfig(string $moduleFolder) : array
    {
        $configVars = [];
        $filesIterator = $this->getFinder()
            ->files()
            ->name('*.yml')
            ->depth('== 0')
            ->in($moduleFolder . '/config');
        foreach ($filesIterator as $file) {
            $configVars = array_merge($configVars, Yaml::parseFile($file->getPathName()));
        }

        return $configVars;
    }
}
