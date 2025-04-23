<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * Class Kernel.
 */
class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /** @var Kernel */
    private static $instance;

    /** @var string cache path */
    private $cacheDir = '/var/cache/centreon/symfony';

    /** @var string Log path */
    private $logDir = '/var/log/centreon/symfony';

    /**
     * Kernel constructor.
     *
     * @param string $environment
     * @param bool $debug
     */
    public function __construct(string $environment, bool $debug)
    {
        parent::__construct($environment, $debug);
        if (defined('_CENTREON_LOG_')) {
            $this->logDir = _CENTREON_LOG_ . '/symfony';
        }
        if (defined('_CENTREON_CACHEDIR_')) {
            $this->cacheDir = _CENTREON_CACHEDIR_ . '/symfony';
        }
    }

    /**
     * @return Kernel
     */
    public static function createForWeb(): Kernel
    {
        if (self::$instance === null) {
            include_once \dirname(__DIR__, 2) . '/config/bootstrap.php';
            if ($_SERVER['APP_DEBUG']) {
                umask(0000);

                Debug::enable();
            }
            self::$instance = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
            self::$instance->boot();
        }

        return self::$instance;
    }

    /**
     * @return iterable<mixed>
     */
    public function registerBundles(): iterable
    {
        $contents = require $this->getProjectDir() . '/config/bundles.php';
        foreach ($contents as $class => $envs) {
            if ($envs[$this->environment] ?? $envs['all'] ?? false) {
                yield new $class();
            }
        }
    }

    /**
     * @return string
     */
    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }

    /**
     * @return string
     */
    public function getCacheDir(): string
    {
        return $this->cacheDir;
    }

    /**
     * @return string
     */
    public function getLogDir(): string
    {
        return $this->logDir;
    }
}
