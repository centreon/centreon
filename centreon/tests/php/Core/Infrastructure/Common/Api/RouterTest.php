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

    $request = new Request(server: [
        'HTTP_HOST' => 'localhost',
        'HTTPS' => 'off',
        'SERVER_PORT' => '80',
    ]);

    $this->router = new Router(
        $this->createMock(Symfony\Component\Routing\RouterInterface::class),
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
