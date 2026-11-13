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
use Core\Gorgone\Infrastructure\Repository\ApiReadGorgoneJobRepository;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

beforeEach(function (): void {
    $this->requests = [];
    $this->answer = ['{"id": "job-42", "state": "succeeded", "events": []}', 200];
    $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
        $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];
        if ($this->answer instanceof \Throwable) {
            throw $this->answer;
        }

        return new MockResponse($this->answer[0], ['http_code' => $this->answer[1]]);
    });

    $loader = $this->createMock(ConfigurationLoaderApiInterface::class);
    $loader->method('getApiIpAddress')->willReturn('10.0.0.5');
    $loader->method('getApiPort')->willReturn(8085);
    $loader->method('isApiConnectionSecure')->willReturn(false);
    $loader->method('isSecureConnectionSelfSigned')->willReturn(false);
    $loader->method('getCommandTimeout')->willReturn(5);

    $this->repository = new ApiReadGorgoneJobRepository($httpClient, $loader, 0);
});

it('should long poll the job for the given time', function (): void {
    $outcome = $this->repository->findOutcome('job-42', 12);

    expect($outcome->getJobId())->toBe('job-42')
        ->and($outcome->isSucceeded())->toBeTrue()
        ->and($this->requests)->toHaveCount(1)
        ->and($this->requests[0]['method'])->toBe('GET')
        ->and($this->requests[0]['url'])->toBe('http://10.0.0.5:8085/v1/jobs/job-42?wait=12')
        // The request lasts as long as the wait: the Gorgone command timeout comes on top of it.
        ->and($this->requests[0]['options']['timeout'])->toBe(17.0)
        ->and($this->requests[0]['options']['max_duration'])->toBe(17.0);
});

it('should read the state at once when there is no time left', function (): void {
    $this->repository->findOutcome('job-42', -3);

    expect($this->requests[0]['url'])->toBe('http://10.0.0.5:8085/v1/jobs/job-42?wait=0')
        ->and($this->requests[0]['options']['timeout'])->toBe(5.0);
});

it('should tell a job still in progress from a finished one', function (string $state, bool $isPending): void {
    $this->answer = [json_encode(['id' => 'job-42', 'state' => $state, 'events' => []]), 200];

    $outcome = $this->repository->findOutcome('job-42', 1);

    expect($outcome->isPending())->toBe($isPending)
        ->and($outcome->isFailed())->toBe(! $isPending && $state !== 'succeeded')
        ->and($outcome->isSucceeded())->toBe($state === 'succeeded');
})->with([
    ['queued', true],
    ['dispatched', true],
    ['running', true],
    ['succeeded', false],
    ['failed', false],
    ['expired', false],
    ['cancelled', false],
]);

it('should explain a failure with the message of the last event', function (): void {
    $this->answer = [
        json_encode([
            'id' => 'job-42',
            'state' => 'failed',
            'events' => [
                ['seq' => 0, 'code' => 0, 'data' => ['message' => 'accepted']],
                ['seq' => 1, 'code' => 4, 'data' => ['message' => 'started']],
                ['seq' => 2, 'code' => 1, 'data' => ['message' => "systemctl restart centengine: exit code 1\n", 'exit_code' => 1]],
            ],
        ]),
        200,
    ];

    expect($this->repository->findOutcome('job-42', 1)->getFailureMessage())
        ->toBe('systemctl restart centengine: exit code 1');
});

it('should explain a failure with the state when the last event has no message', function (mixed $events): void {
    $this->answer = [json_encode(['id' => 'job-42', 'state' => 'expired', 'events' => $events]), 200];

    expect($this->repository->findOutcome('job-42', 1)->getFailureMessage())->toBe('expired');
})->with([
    'no event' => [[]],
    'no events field' => [null],
    'no data' => [[['seq' => 1, 'code' => 1]]],
    'no message' => [[['seq' => 1, 'code' => 1, 'data' => ['exit_code' => 1]]]],
]);

it('should fail when the answer is not the state of a job', function (int $statusCode, string $body, string $expected): void {
    $this->answer = [$body, $statusCode];

    expect(fn () => $this->repository->findOutcome('job-42', 1))
        ->toThrow(GorgoneCommandException::class, $expected);
})->with([
    [404, '{"error": "not_found", "message": "unknown job"}', 'HTTP 404 (status of job job-42): not_found: unknown job'],
    [500, '{"error": "internal", "message": "store unavailable"}', 'HTTP 500'],
    [200, 'not json', 'HTTP 200'],
    [200, '{"id": "job-42"}', 'HTTP 200'],
    [200, '{"id": "job-42", "state": "lost"}', 'Unknown Gorgone job state "lost"'],
]);

it('should fail when the API is unreachable, without asking again', function (): void {
    $this->answer = new TransportException('Connection refused');

    expect(fn () => $this->repository->findOutcome('job-42', 1))
        ->toThrow(GorgoneCommandException::class, 'Connection refused')
        // A request which waits is not sent again: the time allowed would be exceeded.
        ->and($this->requests)->toHaveCount(1);
});
