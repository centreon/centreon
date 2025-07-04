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

declare(strict_types=1);

namespace Core\Infrastructure\Common\Api;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

/**
 * Override symfony router to generate base URI.
 */
class Router implements RouterInterface, RequestMatcherInterface, WarmableInterface
{
    use HttpUrlTrait;

    /** @var RouterInterface */
    private RouterInterface $router;

    /** @var RequestMatcherInterface */
    private RequestMatcherInterface $requestMatcher;

    /**
     * MyRouter constructor.
     *
     * @param RouterInterface $router
     * @param RequestMatcherInterface $requestMatcher
     */
    public function __construct(RouterInterface $router, RequestMatcherInterface $requestMatcher)
    {
        $this->router = $router;
        $this->requestMatcher = $requestMatcher;
    }

    /**
     * Get router.
     *
     * @return RouterInterface
     */
    public function getRouter(): RouterInterface
    {
        return $this->router;
    }

    /**
     * {@inheritDoc}
     *
     * @param string $name
     * @param array{
     *   base_uri?: string,
     *   ...<string, mixed>
     * } $parameters
     * @param int $referenceType
     *
     * @throws \Exception
     *
     * @return string
     */
    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        $parameters['base_uri'] ??= $this->getBaseUri();
        $doubleBaseUri = '';
        if (! empty($parameters['base_uri'])) {
            $doubleBaseUri = $parameters['base_uri'] . '/' . $parameters['base_uri'];
            $doubleBaseUri = preg_replace('/(?<!:)(\/{2,})/', '$2/', $doubleBaseUri);

            if ($doubleBaseUri === null) {
                throw new \Exception('Error occured during regular expression search and replace.');
            }

            $parameters['base_uri'] .= '/';
        }

        // Manage URL Generation for HTTPS and Legacy nested route generation calls
        $context = $this->router->getContext();
        if ($_SERVER['REQUEST_SCHEME'] === 'https') {
                $context->setScheme($_SERVER['REQUEST_SCHEME']);
        }
        if ($_SERVER['SERVER_NAME'] !== 'localhost') {
            $context->setHost($_SERVER['SERVER_NAME']);
        }

        $generatedRoute = $this->router->generate($name, $parameters, $referenceType);

        // remove double slashes
        $generatedRoute = preg_replace('/(?<!:)(\/{2,})/', '$2/', $generatedRoute);

        if ($generatedRoute === null) {
            throw new \Exception('Error occured during regular expression search and replace.');
        }

        // remove double identical prefixes due to progressive migration
        $generatedRoute = str_replace($doubleBaseUri, $parameters['base_uri'], $generatedRoute);

        // remove double slashes
        $generatedRoute = preg_replace('/(?<!:)(\/{2,})/', '$2/', $generatedRoute);

        if ($generatedRoute === null) {
            throw new \Exception('Error occured during regular expression search and replace.');
        }

        return $generatedRoute;
    }

    /**
     * {@inheritDoc}
     *
     * @param RequestContext $context
     */
    public function setContext(RequestContext $context): void
    {
        $this->router->setContext($context);
    }

    /**
     * @inheritDoc
     */
    public function getContext(): RequestContext
    {
        return $this->router->getContext();
    }

    /**
     * @inheritDoc
     */
    public function getRouteCollection(): RouteCollection
    {
        return $this->router->getRouteCollection();
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string,mixed>
     */
    public function match(string $pathinfo): array
    {
        return $this->router->match($pathinfo);
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string,mixed>
     */
    public function matchRequest(Request $request): array
    {
        return $this->requestMatcher->matchRequest($request);
    }

    /**
     * @param string $cacheDir
     * @param null|string $buildDir
     *
     * @return string[]
     */
    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        return [];
    }

    /**
     * Create a href to a legacy page.
     *
     * @param int $topologyPage
     * @param array<string, mixed> $options
     *
     * @return string
     */
    public function generateLegacyHref(int $topologyPage, array $options = []): string
    {
        return $options === []
            ? $this->getBaseUrl() . '/main.php?p=' . $topologyPage
            : $this->getBaseUrl() . '/main.php?p=' . $topologyPage . '&' . http_build_query($options);
    }
}
