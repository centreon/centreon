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

namespace Tests\Core\Installation\Infrastructure;

use App\Shared\Infrastructure\EngineContextFile;
use Core\Engine\Application\Repository\EngineRepositoryInterface;
use Core\Engine\Domain\Model\EngineSecrets;
use Core\Installation\Infrastructure\InstallationHelper;
use Security\Encryption;

beforeEach(function (): void {
    $this->engineRepository = $this->createMock(EngineRepositoryInterface::class);
    $this->encryption = new Encryption();

    $this->helper = fn (bool $isLocalEngineOptional): InstallationHelper => new InstallationHelper(
        $this->engineRepository,
        $this->encryption,
        'app-secret',
        new EngineContextFile('/custom/engine-context.json', '/etc/centreon', $isLocalEngineOptional)
    );
});

it('gives the decided path', function (): void {
    expect(($this->helper)(false)->getEngineContextPath())->toBe('/custom/engine-context.json');
});

it('never creates the file on demand without the feature flag', function (): void {
    $this->engineRepository->expects($this->never())->method('engineSecretsHasContent');
    $this->engineRepository->expects($this->never())->method('writeEngineSecrets');

    expect(($this->helper)(false)->createEngineContextFileOnDemand())->toBeFalse();
});

it('keeps existing secrets with the feature flag', function (): void {
    $this->engineRepository->method('engineSecretsHasContent')->willReturn(true);
    $this->engineRepository->expects($this->never())->method('writeEngineSecrets');

    expect(($this->helper)(true)->createEngineContextFileOnDemand())->toBeFalse();
});

it('creates the secrets like the installation step with the feature flag when none exists', function (): void {
    $this->engineRepository->method('engineSecretsHasContent')->willReturn(false);
    $this->engineRepository
        ->expects($this->once())
        ->method('writeEngineSecrets')
        ->with($this->callback(
            fn (EngineSecrets $secrets): bool => $secrets->firstKey->key === 'app-secret'
                && mb_strlen($secrets->secondKey->key) === 64
        ));

    expect(($this->helper)(true)->createEngineContextFileOnDemand())->toBeTrue();
});
