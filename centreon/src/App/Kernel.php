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

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * Legacy Kernel.
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
     */
    public function __construct(string $environment, bool $debug)
    {
        parent::__construct($environment, $debug);
        if (\defined('_CENTREON_LOG_')) {
            $this->logDir = _CENTREON_LOG_ . '/symfony';
        }
        if (\defined('_CENTREON_CACHEDIR_')) {
            $this->cacheDir = _CENTREON_CACHEDIR_ . '/symfony';
        }
    }

    public static function createForWeb(): self
    {
        if (null === self::$instance) {
            include_once \dirname(__DIR__, 2) . '/config/bootstrap.php';
            if (isset($_SERVER['APP_DEBUG']) && '1' === $_SERVER['APP_DEBUG']) {
                umask(0000);
                Debug::enable();
            } else {
                $_SERVER['APP_DEBUG'] = '0';
            }
            $env = (isset($_SERVER['APP_ENV']) && is_scalar($_SERVER['APP_ENV']))
                ? (string) $_SERVER['APP_ENV']
                : 'prod';
            self::$instance = new self($env, (bool) $_SERVER['APP_DEBUG']);
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
        if (! is_array($contents)) {
            return;
        }
        foreach ($contents as $class => $envs) {
            if ((is_array($envs) && (($envs[$this->environment] ?? $envs['all'] ?? false)))) {
                yield new $class();
            }
        }
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }

    public function getCacheDir(): string
    {
        return $this->cacheDir;
    }

    public function getLogDir(): string
    {
        return $this->logDir;
    }
}
