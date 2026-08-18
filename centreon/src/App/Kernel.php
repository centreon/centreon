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
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * Legacy Kernel.
 */
class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    private static ?Kernel $instance = null;

    /** @var string cache path */
    private string $cacheDir = '/var/cache/centreon/symfony';

    /** @var string|null memoized config file set fingerprint */
    private ?string $configFingerprint = null;

    /**
     * Kernel constructor.
     */
    public function __construct(string $environment, bool $debug)
    {
        parent::__construct($environment, $debug);
        if (\defined('_CENTREON_CACHEDIR_')) {
            $this->cacheDir = _CENTREON_CACHEDIR_ . '/symfony';
        }
    }

    public static function createForWeb(): self
    {
        if (! self::$instance instanceof self) {
            include_once \dirname(__DIR__, 2) . '/config/bootstrap.php';
            if (isset($_SERVER['APP_DEBUG']) && $_SERVER['APP_DEBUG'] === '1') {
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
            $request = Request::createFromGlobals();
            /** @var RequestStack $requestStack */
            $requestStack = self::$instance->getContainer()->get('request_stack');
            $requestStack->push($request);
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

    #[\Override]
    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }

    #[\Override]
    public function getCacheDir(): string
    {
        return $this->cacheDir . '/' . $this->getConfigFingerprint();
    }

    #[\Override]
    public function getLogDir(): string
    {
        return defined('_CENTREON_LOG_') ? (string) _CENTREON_LOG_ : '/var/log/centreon';
    }

    /**
     * Modules drop yaml files under config/routes and config/packages after the container
     * may already have been compiled. Keying the cache directory on the config file set
     * makes such a stale container unreachable instead of fatal.
     */
    private function getConfigFingerprint(): string
    {
        if ($this->configFingerprint === null) {
            $files = glob($this->getProjectDir() . '/config/{routes,packages}/*.yaml', \GLOB_BRACE) ?: [];
            $entries = array_map(
                static fn (string $file): string => $file . ':' . (string) @filemtime($file),
                $files
            );
            $this->configFingerprint = substr(md5(implode('|', $entries)), 0, 8);
        }

        return $this->configFingerprint;
    }

    protected function build(ContainerBuilder $container): void
    {
        $class = 'CentreonAnomalyDetection\DependencyInjection\TagIndicatorPass';

        if (class_exists($class)) {
            /** @var CompilerPassInterface $compilerPass */
            $compilerPass = new $class();
            $container->addCompilerPass($compilerPass);
        }
    }
}
