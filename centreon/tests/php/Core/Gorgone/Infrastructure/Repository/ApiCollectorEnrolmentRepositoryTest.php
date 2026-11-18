<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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
use Core\Gorgone\Domain\Model\CollectorEnrolment;
use Core\Gorgone\Infrastructure\Repository\ApiCollectorEnrolmentRepository;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

beforeEach(function (): void {
    $this->requests = [];
    $this->answer = ['{}', 200];
    $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
        $this->requests[] = ['method' => $method, 'url' => $url, 'body' => $options['body'] ?? ''];

        return new MockResponse($this->answer[0], ['http_code' => $this->answer[1]]);
    });
    $loader = $this->createMock(ConfigurationLoaderApiInterface::class);
    $loader->method('getApiIpAddress')->willReturn('gorgone');
    $loader->method('getApiPort')->willReturn(8085);
    $loader->method('isApiConnectionSecure')->willReturn(false);
    $loader->method('getCommandTimeout')->willReturn(5);
    $this->repository = new ApiCollectorEnrolmentRepository($httpClient, $loader, 0);
});

it('records a token by its hash only', function (): void {
    $this->answer = ['{"id":"t1"}', 201];
    $this->repository->addToken('t1', str_repeat('ab', 32), 12, 'collector-1', 86400, null);

    expect($this->requests[0]['method'])->toBe('PUT')
        ->and($this->requests[0]['url'])->toBe('http://gorgone:8085/v1/enrolment-tokens/t1')
        ->and(json_decode($this->requests[0]['body'], true))->toBe([
            'token_hash' => str_repeat('ab', 32),
            'node_id' => '12',
            'name' => 'collector-1',
            'expires_in_s' => 86400,
        ]);
});

it('reads the enrolments, poller ids only', function (): void {
    $this->answer = [json_encode(['enrolments' => [
        ['id' => 'e1', 'state' => 'pending', 'node_id' => null, 'name' => 'web', 'address' => '10.0.0.1', 'agent_version' => '0.1.0'],
        ['id' => 'e2', 'state' => 'enrolled', 'node_id' => '7', 'name' => 'c7', 'address' => 'c7', 'agent_version' => '0.1.0'],
        ['id' => 'e3', 'state' => 'enrolled', 'node_id' => 'not-a-poller', 'name' => 'x', 'address' => 'x', 'agent_version' => ''],
    ]]), 200];

    $enrolments = $this->repository->findEnrolments();

    expect($enrolments)->toHaveCount(3)
        ->and($enrolments[0]->state)->toBe(CollectorEnrolment::STATE_PENDING)
        ->and($enrolments[0]->pollerId)->toBeNull()
        ->and($enrolments[1]->pollerId)->toBe(7)
        ->and($enrolments[1]->isBound())->toBeTrue()
        ->and($enrolments[2]->isBound())->toBeFalse();
});

it('tells connected nodes and whether their engine applied a configuration', function (): void {
    $this->answer = [json_encode(['nodes' => [
        ['id' => '7', 'connected' => true, 'applied' => ['engine' => 'abc']],
        ['id' => '8', 'connected' => true, 'applied' => []],
        ['id' => 'other', 'connected' => true, 'applied' => []],
    ]]), 200];

    expect($this->repository->findNodeStates())->toBe([
        7 => ['connected' => true, 'configured' => true],
        8 => ['connected' => true, 'configured' => false],
    ]);
});

it('assigns, completes and deletes', function (): void {
    $this->repository->assign('e1', 9, 'web');
    $this->answer = ['', 204];
    $this->repository->complete('e1');
    $this->repository->deleteNode(9);
    // Already gone: not an error.
    $this->answer = ['{"error":"not_found"}', 404];
    $this->repository->deleteNode(9);

    expect(array_column($this->requests, 'url'))->toBe([
        'http://gorgone:8085/v1/enrolments/e1/assign',
        'http://gorgone:8085/v1/enrolments/e1/complete',
        'http://gorgone:8085/v1/nodes/9',
        'http://gorgone:8085/v1/nodes/9',
    ])->and(json_decode($this->requests[0]['body'], true))->toBe(['node_id' => '9', 'name' => 'web']);
});

it('reports a refused token', function (): void {
    $this->answer = ['{"error":"conflict","message":"this token is already registered"}', 409];

    expect(fn () => $this->repository->addToken('t1', str_repeat('ab', 32), null, null, 60, 3))
        ->toThrow(GorgoneCommandException::class, 'already registered');
});
