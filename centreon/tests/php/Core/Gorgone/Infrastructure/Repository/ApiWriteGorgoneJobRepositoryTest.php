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

namespace Tests\Core\Gorgone\Infrastructure\Repository;

use Centreon\Infrastructure\Gorgone\Interfaces\ConfigurationLoaderApiInterface;
use Core\Gorgone\Application\Exception\GorgoneCommandException;
use Core\Gorgone\Domain\Model\TarArchive;
use Core\Gorgone\Infrastructure\Repository\ApiWriteGorgoneJobRepository;
use Symfony\Component\HttpClient\Exception\TimeoutException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

beforeEach(function (): void {
    $this->requests = [];
    // Answers to the next requests ([body, HTTP status code] or an error), then the default one.
    $this->answers = [];
    $this->answer = ['{"id": "job-42"}', 202];
    $this->httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
        $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];
        $answers = $this->answers;
        $answer = array_shift($answers) ?? $this->answer;
        $this->answers = $answers;
        if ($answer instanceof \Throwable) {
            throw $answer;
        }

        return new MockResponse($answer[0], ['http_code' => $answer[1]]);
    });

    $this->createRepository = function (array $configuration = []): ApiWriteGorgoneJobRepository {
        $configuration += [
            'address' => '10.0.0.5',
            'port' => 8085,
            'username' => null,
            'password' => null,
            'ssl' => false,
            'selfSigned' => false,
            'timeout' => 5,
        ];
        $loader = $this->createMock(ConfigurationLoaderApiInterface::class);
        $loader->method('getApiIpAddress')->willReturn($configuration['address']);
        $loader->method('getApiPort')->willReturn($configuration['port']);
        $loader->method('getApiUsername')->willReturn($configuration['username']);
        $loader->method('getApiPassword')->willReturn($configuration['password']);
        $loader->method('isApiConnectionSecure')->willReturn($configuration['ssl']);
        $loader->method('isSecureConnectionSelfSigned')->willReturn($configuration['selfSigned']);
        $loader->method('getCommandTimeout')->willReturn($configuration['timeout']);

        // No pause before a request is sent again.
        return new ApiWriteGorgoneJobRepository($this->httpClient, $loader, 0);
    };

    $this->findHeader = function (string $name): ?string {
        foreach ($this->requests[0]['options']['headers'] as $header) {
            if (mb_stripos($header, $name . ':') === 0) {
                return trim(mb_substr($header, mb_strlen($name) + 1));
            }
        }

        return null;
    };

    // Id of the job in a request: in the body of a job submission, in the query of a configuration push.
    $this->findJobId = function (int $index): ?string {
        $request = $this->requests[$index];
        if ($request['method'] === 'PUT') {
            parse_str((string) parse_url($request['url'], PHP_URL_QUERY), $query);

            return $query['id'] ?? null;
        }

        return json_decode($request['options']['body'], true)['id'] ?? null;
    };
});

it('should submit the external commands as an engine_commands job', function (): void {
    $jobId = ($this->createRepository)()->submitEngineCommands(
        3,
        ['[1700000000] ACKNOWLEDGE_HOST_PROBLEM;host;2;0;1;admin;comment', '[1700000001] ENABLE_NOTIFICATIONS']
    );

    $body = json_decode($this->requests[0]['options']['body'], true);
    expect($jobId)->toBe('job-42')
        ->and($this->requests)->toHaveCount(1)
        ->and($this->requests[0]['method'])->toBe('POST')
        ->and($this->requests[0]['url'])->toBe('http://10.0.0.5:8085/v1/nodes/3/jobs')
        ->and(array_keys($body))->toBe(['id', 'engine_commands'])
        ->and($body['engine_commands'])->toBe([
            'commands' => [
                '[1700000000] ACKNOWLEDGE_HOST_PROBLEM;host;2;0;1;admin;comment',
                '[1700000001] ENABLE_NOTIFICATIONS',
            ],
        ])
        ->and(($this->findHeader)('Content-Type'))->toBe('application/json')
        ->and(($this->findHeader)('Authorization'))->toBeNull()
        ->and($this->requests[0]['options']['timeout'])->toBe(5.0)
        ->and($this->requests[0]['options']['verify_peer'])->toBeTrue()
        ->and($this->requests[0]['options']['verify_host'])->toBeTrue();
});

it('should submit a service control job', function (): void {
    $jobId = ($this->createRepository)()->submitServiceControl(1, 'broker', 'reload');

    $body = json_decode($this->requests[0]['options']['body'], true);
    expect($jobId)->toBe('job-42')
        ->and($this->requests[0]['method'])->toBe('POST')
        ->and($this->requests[0]['url'])->toBe('http://10.0.0.5:8085/v1/nodes/1/jobs')
        ->and(array_keys($body))->toBe(['id', 'service_control'])
        ->and($body['service_control'])->toBe(['service' => 'broker', 'operation' => 'reload']);
});

it('should name the configurations the job depends on next to the action, only when there are some', function (): void {
    ($this->createRepository)()->submitServiceControl(1, 'engine', 'restart', ['engine', 'broker']);
    ($this->createRepository)()->submitServiceControl(1, 'engine', 'stop', []);

    $required = json_decode($this->requests[0]['options']['body'], true);
    $notRequired = json_decode($this->requests[1]['options']['body'], true);
    expect($required['requires_latest_config'])->toBe(['engine', 'broker'])
        ->and($required['service_control'])->toBe(['service' => 'engine', 'operation' => 'restart'])
        ->and($notRequired)->not->toHaveKey('requires_latest_config');
});

it('should push a configuration as an uncompressed tar archive', function (): void {
    $archive = new TarArchive(['centengine.cfg' => 'engine', 'hosts.cfg' => 'hosts']);

    $jobId = ($this->createRepository)()->pushConfiguration(7, 'engine', $archive);

    $body = $this->requests[0]['options']['body'];
    expect($jobId)->toBe('job-42')
        ->and($this->requests[0]['method'])->toBe('PUT')
        ->and($this->requests[0]['url'])->toStartWith('http://10.0.0.5:8085/v1/nodes/7/config/engine?id=')
        ->and(($this->findHeader)('Content-Type'))->toBe('application/x-tar')
        ->and(mb_strlen($body, '8bit'))->toBe(512 * 6)
        ->and(rtrim(mb_substr($body, 0, 100, '8bit'), "\0"))->toBe('centengine.cfg')
        ->and(mb_substr($body, 512, 6, '8bit'))->toBe('engine')
        ->and(rtrim(mb_substr($body, 1024, 100, '8bit'), "\0"))->toBe('hosts.cfg');
});

it('should give each job its own random id', function (): void {
    $repository = ($this->createRepository)();
    $repository->submitServiceControl(1, 'engine', 'reload', ['engine', 'broker']);
    $repository->submitServiceControl(1, 'engine', 'reload', ['engine', 'broker']);
    $repository->submitEngineCommands(1, ['[1700000000] ENABLE_NOTIFICATIONS']);
    $repository->pushConfiguration(1, 'engine', new TarArchive(['a.cfg' => 'a']));

    $jobIds = array_map($this->findJobId, array_keys($this->requests));
    expect($jobIds)->toHaveCount(4)
        ->and(array_unique($jobIds))->toHaveCount(4)
        ->and($jobIds)->each->toMatch('/^[0-9a-f]{32}$/');
});

it('should send a request again once, with the same id', function (\Throwable|array $firstAnswer, string $method): void {
    $this->answers = [$firstAnswer];
    $repository = ($this->createRepository)();

    $jobId = $method === 'PUT'
        ? $repository->pushConfiguration(1, 'engine', new TarArchive(['a.cfg' => 'a']))
        : $repository->submitServiceControl(1, 'engine', 'restart', ['engine', 'broker']);

    expect($jobId)->toBe('job-42')
        ->and($this->requests)->toHaveCount(2)
        ->and(($this->findJobId)(0))->toMatch('/^[0-9a-f]{32}$/')
        ->and(($this->findJobId)(1))->toBe(($this->findJobId)(0))
        ->and($this->requests[1]['url'])->toBe($this->requests[0]['url'])
        ->and($this->requests[1]['options']['body'])->toBe($this->requests[0]['options']['body']);
})->with([
    'connection error' => [new TransportException('Connection refused'), 'POST'],
    'timeout' => [new TimeoutException('Idle timeout reached'), 'POST'],
    'HTTP 500' => [['{"error": "internal", "message": "store unavailable"}', 500], 'POST'],
    'HTTP 503' => [['', 503], 'POST'],
    'configuration push' => [new TransportException('Connection reset'), 'PUT'],
    'configuration push, HTTP 502' => [['Bad Gateway', 502], 'PUT'],
]);

it('should not send a request again after an HTTP 4xx answer', function (int $statusCode, string $expected): void {
    $this->answers = [['{"error": "refused", "message": "no"}', $statusCode]];

    expect(fn () => ($this->createRepository)()->submitServiceControl(9, 'engine', 'reload', ['engine', 'broker']))
        ->toThrow(GorgoneCommandException::class, $expected)
        ->and($this->requests)->toHaveCount(1);
})->with([
    [400, 'HTTP 400'],
    [401, 'HTTP 401'],
    [404, 'Poller #9 is not known by Gorgone'],
    [409, 'HTTP 409'],
    [422, 'HTTP 422'],
]);

it('should give up after the second failure', function (\Throwable|array $answer, string $expected): void {
    $this->answer = $answer;

    expect(fn () => ($this->createRepository)()->submitServiceControl(1, 'engine', 'reload', ['engine', 'broker']))
        ->toThrow(GorgoneCommandException::class, $expected)
        ->and($this->requests)->toHaveCount(2);
})->with([
    'connection error' => [new TransportException('Connection refused'), 'Connection refused'],
    'timeout' => [new TimeoutException('Idle timeout reached'), 'Idle timeout reached'],
    'HTTP 500' => [['{"error": "internal", "message": "store unavailable"}', 500], 'HTTP 500 (reload of engine for poller #1): internal: store unavailable'],
]);

it('should use the HTTP basic authentication when a username is set', function (): void {
    ($this->createRepository)(['username' => 'centreon', 'password' => 'p@ss:word'])
        ->submitServiceControl(1, 'engine', 'reload');

    expect(($this->findHeader)('Authorization'))->toBe('Basic ' . base64_encode('centreon:p@ss:word'));
});

it('should not authenticate when the username is empty', function (): void {
    ($this->createRepository)(['username' => '', 'password' => 'ignored'])
        ->submitServiceControl(1, 'engine', 'reload');

    expect(($this->findHeader)('Authorization'))->toBeNull();
});

it('should use https and accept a self-signed certificate according to the options', function (): void {
    ($this->createRepository)(['ssl' => true, 'selfSigned' => true, 'port' => 8443, 'address' => 'gorgone'])
        ->submitServiceControl(1, 'engine', 'reload');

    expect($this->requests[0]['url'])->toBe('https://gorgone:8443/v1/nodes/1/jobs')
        ->and($this->requests[0]['options']['verify_peer'])->toBeFalse()
        ->and($this->requests[0]['options']['verify_host'])->toBeFalse();
});

it('should fail when the node is unknown', function (): void {
    $this->answer = ['{"error": "not_found", "message": "unknown node"}', 404];

    ($this->createRepository)()->submitServiceControl(9, 'engine', 'reload');
})->throws(GorgoneCommandException::class, 'Poller #9 is not known by Gorgone');

it('should fail with the error of the API', function (int $statusCode, string $body, string $expected): void {
    $this->answer = [$body, $statusCode];

    expect(fn () => ($this->createRepository)()->pushConfiguration(1, 'engine', new TarArchive(['a.cfg' => 'a'])))
        ->toThrow(GorgoneCommandException::class, $expected);
})->with([
    [400, '{"error": "bad_request", "message": "invalid bundle"}', 'HTTP 400 (engine configuration for poller #1): bad_request: invalid bundle'],
    [401, '', 'HTTP 401'],
    [415, 'Unsupported Media Type', 'HTTP 415'],
    [500, '{"error": "internal", "message": "store unavailable"}', 'internal: store unavailable'],
    [202, '{"token": "legacy"}', 'HTTP 202'],
    [200, 'not json', 'HTTP 200'],
]);

it('should fail when the API is unreachable', function (): void {
    $this->answer = new TransportException('Connection refused');

    ($this->createRepository)()->submitEngineCommands(1, ['[1700000000] ENABLE_NOTIFICATIONS']);
})->throws(GorgoneCommandException::class, 'Connection refused');

it('should fail without any request when the address is not configured', function (): void {
    expect(fn () => ($this->createRepository)(['address' => null])->submitServiceControl(1, 'engine', 'reload'))
        ->toThrow(GorgoneCommandException::class, 'address of the Gorgone API is not configured')
        ->and($this->requests)->toBe([]);
});
