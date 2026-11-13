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

namespace Tests\Core\Engine\Infrastructure\Repository;

use App\Shared\Infrastructure\EngineContextFile;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Engine\Application\Exception\EngineSecretsDoesNotExistException;
use Core\Engine\Domain\Model\EngineKey;
use Core\Engine\Domain\Model\EngineSecrets;
use Core\Engine\Infrastructure\Repository\FsEngineRepository;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\SerializerInterface;

beforeEach(function (): void {
    $this->root = sys_get_temp_dir() . '/engine_repository_' . bin2hex(random_bytes(6));
    $this->engineFile = $this->root . '/centreon-engine/engine-context.json';
    $this->webDirectory = $this->root . '/centreon';
    $this->webFile = $this->webDirectory . '/engine-context.json';
    mkdir($this->webDirectory, 0770, true);

    $this->serializer = $this->createMock(SerializerInterface::class);
    $this->serializer->method('serialize')->willReturn('{"app_secret":"secret","salt":"salt"}');

    $this->repository = fn (bool $isLocalEngineOptional, string $configuredPath = ''): FsEngineRepository => new FsEngineRepository(
        new EngineContextFile($configuredPath, $this->webDirectory, $isLocalEngineOptional, $this->engineFile),
        $this->serializer
    );
    $this->secrets = new EngineSecrets(new EngineKey('secret'), new EngineKey('salt'));
});

afterEach(function (): void {
    (new Filesystem())->remove($this->root);
});

it('reads and writes the file of the engine package by default', function (): void {
    mkdir(dirname($this->engineFile));
    touch($this->engineFile);
    chmod($this->engineFile, 0660);
    $repository = ($this->repository)(false);

    expect($repository->engineSecretsHasContent())->toBeFalse();
    $repository->writeEngineSecrets($this->secrets);

    clearstatcache();
    expect(fileperms($this->engineFile) & 0777)->toBe(0660)
        ->and($repository->engineSecretsHasContent())->toBeTrue()
        ->and($repository->getEngineSecrets()->firstKey->key)->toBe('secret')
        ->and(file_exists($this->webFile))->toBeFalse();
});

it('uses the path set by the environment variable', function (): void {
    $configured = $this->root . '/custom.json';
    file_put_contents($configured, '{"app_secret":"custom","salt":"salt"}');

    expect(($this->repository)(false, $configured)->getEngineSecrets()->firstKey->key)->toBe('custom')
        ->and(($this->repository)(true, $configured)->getEngineSecrets()->firstKey->key)->toBe('custom');
});

it('still fails without the feature flag when the engine package did not provide the file', function (): void {
    $repository = ($this->repository)(false);

    expect(fn () => $repository->engineSecretsHasContent())->toThrow(RepositoryException::class)
        ->and(fn () => $repository->getEngineSecrets())->toThrow(EngineSecretsDoesNotExistException::class);
});

it('keeps the file of the engine package with the feature flag when its directory exists', function (): void {
    mkdir(dirname($this->engineFile));
    file_put_contents($this->engineFile, '{"app_secret":"engine","salt":"salt"}');

    expect(($this->repository)(true)->getEngineSecrets()->firstKey->key)->toBe('engine');
});

it('creates a file only its owner and group can read with the feature flag and no engine directory', function (): void {
    $repository = ($this->repository)(true);

    expect($repository->engineSecretsHasContent())->toBeFalse();
    $repository->writeEngineSecrets($this->secrets);

    expect(file_get_contents($this->webFile))->toBe('{"app_secret":"secret","salt":"salt"}')
        ->and(fileperms($this->webFile) & 0777)->toBe(0640)
        ->and($repository->engineSecretsHasContent())->toBeTrue()
        ->and(is_dir(dirname($this->engineFile)))->toBeFalse();
});

it('never overwrites existing secrets', function (): void {
    $repository = ($this->repository)(true);
    $repository->writeEngineSecrets($this->secrets);

    expect(fn () => $repository->writeEngineSecrets($this->secrets))->toThrow(RepositoryException::class);
});
