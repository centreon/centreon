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

use Core\Infrastructure\Common\Api\Router;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RequestContext;

beforeEach(function (): void {
    $this->server = $_SERVER;

    $_SERVER['REQUEST_SCHEME'] = 'http';
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['SERVER_ADDR'] = '127.0.0.1';
    $_SERVER['SERVER_PORT'] = '80';
    unset($_SERVER['HTTP_X_FORWARDED_PROTO']);

    $request = new Request(server: [
        'HTTP_HOST' => 'localhost',
        'HTTPS' => 'off',
        'SERVER_PORT' => '80',
    ]);

    $this->mockRouter = $this->createMock(Symfony\Component\Routing\RouterInterface::class);

    $this->router = new Router(
        $this->mockRouter,
        $this->createMock(Symfony\Component\Routing\Matcher\RequestMatcherInterface::class)
    );
    $requestStack = $this->createMock(Symfony\Component\HttpFoundation\RequestStack::class);
    $requestStack->method('getCurrentRequest')->willReturn($request);
    $this->router->setHttpServerBag($requestStack);
});

afterEach(function (): void {
    $_SERVER = $this->server;
});

it('should return a legacy path without options', function (): void {
    $legacyHref = $this->router->generateLegacyHref(60202);
    expect($legacyHref)->toBe('http://localhost/main.php?p=60202');
});

it('should return a legacy path with options', function (): void {
    $legacyHref = $this->router->generateLegacyHref(60202, ['foo' => 'bar']);
    expect($legacyHref)->toBe('http://localhost/main.php?p=60202&foo=bar');
});

it('should generate a local URL using 127.0.0.1', function (): void {
    $context = new RequestContext();
    $context->setHost('my-server.example.com');
    $context->setScheme('https');
    $context->setHttpPort(443);

    $mockRouter = $this->createMock(Symfony\Component\Routing\RouterInterface::class);
    $mockRouter->method('getContext')->willReturn($context);
    $mockRouter->method('generate')->willReturn('http://127.0.0.1/centreon/api/latest/my-route');

    $request = new Request(server: [
        'HTTP_HOST' => 'my-server.example.com',
        'HTTPS' => 'off',
    ]);

    $router = new Router(
        $mockRouter,
        $this->createMock(Symfony\Component\Routing\Matcher\RequestMatcherInterface::class)
    );
    $requestStack = $this->createMock(Symfony\Component\HttpFoundation\RequestStack::class);
    $requestStack->method('getCurrentRequest')->willReturn($request);
    $router->setHttpServerBag($requestStack);

    $url = $router->generateLocalUrl('my_route');
    expect($url)->toBe('http://127.0.0.1/centreon/api/latest/my-route');
});

it('should restore the original routing context after generating a local URL', function (): void {
    $context = new RequestContext();
    $context->setHost('my-server.example.com');
    $context->setScheme('https');
    $context->setHttpPort(443);

    $mockRouter = $this->createMock(Symfony\Component\Routing\RouterInterface::class);
    $mockRouter->method('getContext')->willReturn($context);
    $mockRouter->method('generate')->willReturn('http://127.0.0.1/api/latest/my-route');

    $request = new Request(server: [
        'HTTP_HOST' => 'my-server.example.com',
        'HTTPS' => 'off',
    ]);

    $router = new Router(
        $mockRouter,
        $this->createMock(Symfony\Component\Routing\Matcher\RequestMatcherInterface::class)
    );
    $requestStack = $this->createMock(Symfony\Component\HttpFoundation\RequestStack::class);
    $requestStack->method('getCurrentRequest')->willReturn($request);
    $router->setHttpServerBag($requestStack);

    $router->generateLocalUrl('my_route');

    expect($context->getHost())->toBe('my-server.example.com')
        ->and($context->getScheme())->toBe('https')
        ->and($context->getHttpPort())->toBe(443);
});

it(
    'preserves generated URLs when the short-name host collides with the base path',
    function (string $baseUri, string $rawUrl, string $expected): void {
        $this->mockRouter->method('generate')->willReturn($rawUrl);

        $result = $this->router->generate(
            'any_route',
            ['base_uri' => $baseUri],
            Router::ABSOLUTE_URL
        );

        expect($result)->toBe($expected);
    }
)->with([
    'short-name host equal to default base path' => [
        '/centreon',
        'https://centreon/centreon/api/latest/configuration/hosts/7013',
        'https://centreon/centreon/api/latest/configuration/hosts/7013',
    ],
    'short-name host equal to custom base path /snc' => [
        '/snc',
        'https://snc/snc/api/latest/configuration/hosts/7013',
        'https://snc/snc/api/latest/configuration/hosts/7013',
    ],
    'domain suffix colliding with custom base path /com' => [
        '/com',
        'https://centreon.com/com/api/latest/configuration/hosts/7013',
        'https://centreon.com/com/api/latest/configuration/hosts/7013',
    ],
]);

it(
    'still collapses the double base path in legitimate URLs',
    function (string $baseUri, int $referenceType, string $rawUrl, string $expected): void {
        $this->mockRouter->method('generate')->willReturn($rawUrl);

        $result = $this->router->generate(
            'any_route',
            ['base_uri' => $baseUri],
            $referenceType
        );

        expect($result)->toBe($expected);
    }
)->with([
    'FQDN with duplicated default base path' => [
        '/centreon',
        Router::ABSOLUTE_URL,
        'https://my-server.example.com/centreon/centreon/api/latest/configuration/hosts/7013',
        'https://my-server.example.com/centreon/api/latest/configuration/hosts/7013',
    ],
    'FQDN with duplicated custom base path /snc' => [
        '/snc',
        Router::ABSOLUTE_URL,
        'https://snc.mj.gouv.fr/snc/snc/api/latest/configuration/hosts/7013',
        'https://snc.mj.gouv.fr/snc/api/latest/configuration/hosts/7013',
    ],
    'relative URL with duplicated default base path' => [
        '/centreon',
        Router::ABSOLUTE_PATH,
        '/centreon/centreon/api/latest/configuration/hosts/7013',
        '/centreon/api/latest/configuration/hosts/7013',
    ],
]);

it('leaves URLs without a doubled base path untouched', function (): void {
    $this->mockRouter->method('generate')
        ->willReturn('https://centreon.test.local/centreon/api/latest/configuration/hosts/7013');

    $result = $this->router->generate(
        'any_route',
        ['base_uri' => '/centreon'],
        Router::ABSOLUTE_URL
    );

    expect($result)->toBe('https://centreon.test.local/centreon/api/latest/configuration/hosts/7013');
});
