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

namespace Tests\Core\AgentConfiguration\Application\UseCase\AddAgentConfiguration;

use Core\AgentConfiguration\Application\Exception\AgentConfigurationException;
use Core\AgentConfiguration\Application\UseCase\AddAgentConfiguration\AddAgentConfigurationRequest;
use Core\AgentConfiguration\Application\Validation\TelegrafValidator;
use Core\AgentConfiguration\Domain\Model\Poller;
use Core\AgentConfiguration\Domain\Model\Type;

beforeEach(function (): void {
    $this->TelegrafValidator = new TelegrafValidator();

    $this->request = new AddAgentConfigurationRequest();
    $this->request->name = 'telegraf-test';
    $this->request->type = 'telegraf';
    $this->request->pollerIds = [1];
    $this->request->configuration = [];

    $this->poller = new Poller(1, 'poller-name');
});

it('should correctly identify that it handles TELEGRAF type', function (): void {
    $result = $this->TelegrafValidator->isValidFor(Type::TELEGRAF);
    expect($result)->toBeTrue();
});

it('should correctly identify that it does not handle other types', function (): void {
    $result = $this->TelegrafValidator->isValidFor(Type::CMA);
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
        $this->request->configuration['conf_certificate'] = $filename;
        $this->expectException(AgentConfigurationException::class);
        $this->TelegrafValidator->validateParametersOrFail($this->request);
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
        $this->request->configuration['otel_ca_certificate'] = $filename;
        $this->TelegrafValidator->validateParametersOrFail($this->request);
    })->expectNotToPerformAssertions();
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
        $this->request->configuration['conf_private_key'] = $filename;
        $this->expectException(AgentConfigurationException::class);
        $this->TelegrafValidator->validateParametersOrFail($this->request);
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
        $this->TelegrafValidator->validateParametersOrFail($this->request);
    })->expectNotToPerformAssertions();
}
