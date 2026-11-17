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

use App\Shared\Infrastructure\EngineContextFile;
use Core\Gorgone\Application\Repository\ReadGorgoneNodeRepositoryInterface;
use Core\Gorgone\Infrastructure\Repository\FsReadGorgoneConfigurationRepository;
use Symfony\Component\Filesystem\Filesystem;

beforeEach(function (): void {
    $this->filesystem = new Filesystem();
    $this->root = sys_get_temp_dir() . '/' . uniqid('gorgone-config-', true);
    $this->cache = $this->root . '/cache';
    $this->traps = $this->root . '/traps';

    $this->filesystem->dumpFile($this->cache . '/config/engine/3/centengine.cfg', 'engine');
    $this->filesystem->dumpFile($this->cache . '/config/engine/3/hosts.cfg', 'hosts');
    $this->filesystem->dumpFile($this->cache . '/config/engine/3/objects/services.cfg', 'services');
    $this->filesystem->symlink('/etc/passwd', $this->cache . '/config/engine/3/link.cfg');
    $this->filesystem->dumpFile($this->cache . '/config/engine/4/centengine.cfg', 'other poller');
    $this->filesystem->dumpFile($this->cache . '/config/broker/3/central-module.json', '{"broker": true}');
    $this->filesystem->dumpFile($this->cache . '/config/vmware/3/centreon_vmware.json', '{"vmware": true}');
    $this->filesystem->dumpFile($this->cache . '/config/vmware/3/other.json', '{}');
    $this->filesystem->dumpFile($this->traps . '/3/centreontrapd.sdb', 'SQLite format 3');

    $nodes = $this->createMock(ReadGorgoneNodeRepositoryInterface::class);
    $nodes->method('findTrapsDirectories')->willReturn([$this->root . '/missing', $this->traps]);

    $this->keyFile = $this->root . '/secrets/web-key.json';
    $this->key = "{\"app_secret\":\"secret\",\"salt\":\"salt\"}\n";
    $this->filesystem->dumpFile($this->keyFile, $this->key);

    $this->createRepository = fn (string $keyFile): FsReadGorgoneConfigurationRepository => new FsReadGorgoneConfigurationRepository(
        $this->cache . '/',
        $nodes,
        new EngineContextFile($keyFile, $this->root, true),
    );
    $this->repository = ($this->createRepository)($this->keyFile);
});

afterEach(function (): void {
    $this->filesystem->remove($this->root);
});

it('should read the regular files of the engine directory with relative names', function (): void {
    expect($this->repository->findFiles(3, 'engine'))->toBe([
        'centengine.cfg' => 'engine',
        'hosts.cfg' => 'hosts',
        'objects/services.cfg' => 'services',
    ]);
});

it('should read the files of the broker directory', function (): void {
    expect($this->repository->findFiles(3, 'broker'))->toBe(['central-module.json' => '{"broker": true}']);
});

it('should read the single vmware file', function (): void {
    expect($this->repository->findFiles(3, 'vmware'))->toBe(['centreon_vmware.json' => '{"vmware": true}']);
});

it('should read the traps database from the first directory holding it', function (): void {
    expect($this->repository->findFiles(3, 'centreontrapd'))->toBe(['centreontrapd.sdb' => 'SQLite format 3']);
});

it('should read the JSON configuration of a poller run by centreon-engine from its engine directory', function (): void {
    $this->filesystem->dumpFile($this->cache . '/config/engine/3/engine-configuration.json', '{"version":1}');

    expect($this->repository->findFiles(3, 'engine_configuration'))
        ->toBe(['engine-configuration.json' => '{"version":1}']);
});

it('should return nothing when the configuration is not generated', function (string $target): void {
    expect($this->repository->findFiles(9, $target))->toBe([]);
})->with(['engine', 'broker', 'vmware', 'centreontrapd', 'engine_configuration']);

it('should read the key file of centreon-web under the name the pollers expect, whatever the node', function (int $nodeId): void {
    expect($this->repository->findFiles($nodeId, 'engine_context'))->toBe(['engine-context.json' => $this->key]);
})->with([3, 9]);

it('should follow a key file which is a symbolic link, as a mounted secret', function (): void {
    $this->filesystem->symlink($this->keyFile, $this->root . '/engine-context.json');

    expect(($this->createRepository)($this->root . '/engine-context.json')->findFiles(3, 'engine_context'))
        ->toBe(['engine-context.json' => $this->key]);
});

it('should return nothing when centreon-web has no key file', function (): void {
    expect(($this->createRepository)($this->root . '/missing.json')->findFiles(3, 'engine_context'))->toBe([]);
});

it('should refuse an unknown target', function (): void {
    $this->repository->findFiles(3, 'export');
})->throws(\InvalidArgumentException::class);
