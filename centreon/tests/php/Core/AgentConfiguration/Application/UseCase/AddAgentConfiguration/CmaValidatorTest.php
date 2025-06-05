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

namespace Tests\Core\AgentConfiguration\Application\UseCase\AddAgentConfiguration;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\AgentConfiguration\Application\Exception\AgentConfigurationException;
use Core\AgentConfiguration\Application\UseCase\AddAgentConfiguration\AddAgentConfigurationRequest;
use Core\AgentConfiguration\Application\Validation\CmaValidator;
use Core\AgentConfiguration\Domain\Model\ConnectionModeEnum;
use Core\AgentConfiguration\Domain\Model\Poller;
use Core\AgentConfiguration\Domain\Model\Type;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Security\Token\Application\Repository\ReadTokenRepositoryInterface;

beforeEach(function (): void {
    $this->cmaValidator = new CmaValidator(
        $this->readHostRepository = $this->createMock(ReadHostRepositoryInterface::class),
        $this->readTokenRepository = $this->createMock(ReadTokenRepositoryInterface::class),
        $this->user = $this->createMock(ContactInterface::class),
    );

    $this->request = new AddAgentConfigurationRequest();
    $this->request->name = 'cmatest';
    $this->request->type = 'centeron-agent';
    $this->request->pollerIds = [1];
    $this->request->connectionMode = ConnectionModeEnum::SECURE;
    $this->request->configuration = [
        'is_reverse' => true,
        'otel_public_certificate' => '/etc/pki/test.crt',
        'otel_private_key' => '/etc/pki/test.key',
        'otel_ca_certificate' => '/etc/pki/test.cer',
        'tokens' => [],
        'hosts' => [
            [
                'address' => '',
                'port' => 0,
                'poller_ca_certificate' => '/etc/pki/test.cer',
                'poller_ca_name' => 'poller-name',
            ],
        ],
    ];

    $this->poller = new Poller(1, 'poller-name');
});

it('should correctly identify that it handles CMA type', function (): void {
    $result = $this->cmaValidator->isValidFor(Type::CMA);
    expect($result)->toBeTrue();
});

it('should correctly identify that it does not handle other types', function (): void {
    $result = $this->cmaValidator->isValidFor(Type::TELEGRAF);
    expect($result)->toBeFalse();
});

foreach (
    [
        'invalidfilename',
        './fileName.crt',
        '../fileName.cer',
        '//fileName.crt',
        '/etc/pki/test.txt',
        '/etc/pki/test.doc',
    ] as $filename
) {
    it("should throw an exception because of the filename for certificate {$filename} invalidity", function () use ($filename): void {
        $this->request->configuration['otel_ca_certificate'] = $filename;
        $this->expectException(AgentConfigurationException::class);
        $this->cmaValidator->validateParametersOrFail($this->request);
    });
}

foreach (
    [
        '/etc/pki/test.crt',
        '/etc/pki/test.cer',
        'test.crt',
        'test.cer',
    ] as $filename
) {
    it("should not throw an exception when the filename for certificate {$filename} is valid", function () use ($filename): void {
        $this->request->configuration['hosts'][] = [
            'poller_ca_certificate' => $filename,
            'id' => 9999,
        ] ;
        $this->readHostRepository
            ->expects($this->once())
            ->method('exists')
            ->willReturn(true);
        $this->cmaValidator->validateParametersOrFail($this->request);
    });
}
foreach (
    [
        'invalidfilename',
        './fileName.key',
        '../fileName.key',
        '//fileName.key',
        '/etc/pki/test.txt',
        '/etc/pki/test.doc',
    ] as $filename
) {
    it("should throw an exception because of the filename for key {$filename} invalidity", function () use ($filename): void {
        $this->request->configuration['otel_private_key'] = $filename;
        $this->expectException(AgentConfigurationException::class);
        $this->cmaValidator->validateParametersOrFail($this->request);
    });
}

foreach (
    [
        '/etc/pki/test.key',
        'test.key',
    ] as $filename
) {
    it("should not throw an exception when the filename for key {$filename} is valid", function () use ($filename): void {
        $this->request->configuration['otel_private_key'] = $filename;
        $this->cmaValidator->validateParametersOrFail($this->request);
    })->expectNotToPerformAssertions();
}

it("should throw an exception when a token is not provided and connection is not no_tls or reverse", function (): void {
    $this->request->configuration['is_reverse'] = false;
    $this->expectException(AgentConfigurationException::class);
    $this->cmaValidator->validateParametersOrFail($this->request);
});

it("should throw an exception when a token is provided but invalid and connection is not no_tls or reverse", function (): void {
    $this->request->configuration['is_reverse'] = false;
    $this->request->configuration['tokens'] = [['name' => 'tokenName', 'creator_id' => 1]];
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->readTokenRepository
        ->expects($this->once())
        ->method('findByNameAndUserId')
        ->willReturn(null);
    $this->expectException(AgentConfigurationException::class);
    $this->cmaValidator->validateParametersOrFail($this->request);
});

it('should throw an exception when the host id is invalid', function (): void {
    $this->request->configuration['hosts'] = [
        [
            'id' => 9999,
            'poller_ca_certificate' => null,
        ]
    ];
    $this->readHostRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $this->cmaValidator->validateParametersOrFail($this->request);
})->throws((AgentConfigurationException::invalidHostId(9999)->getMessage()));
