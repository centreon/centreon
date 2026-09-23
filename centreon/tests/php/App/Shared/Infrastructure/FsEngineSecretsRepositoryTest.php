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

namespace Tests\App\Shared\Infrastructure;

use App\Shared\Domain\Exception\EngineSecretsUnavailableException;
use App\Shared\Infrastructure\FsEngineSecretsRepository;
use PHPUnit\Framework\TestCase;

final class FsEngineSecretsRepositoryTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'engine_context_');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testGetAppSecretReturnsValue(): void
    {
        file_put_contents($this->tempFile, json_encode([
            'app_secret' => 'my-app-secret',
            'salt' => 'my-salt',
        ]));

        $repository = new FsEngineSecretsRepository($this->tempFile);

        self::assertSame('my-app-secret', $repository->getAppSecret());
    }

    public function testGetSaltReturnsValue(): void
    {
        file_put_contents($this->tempFile, json_encode([
            'app_secret' => 'my-app-secret',
            'salt' => 'my-salt',
        ]));

        $repository = new FsEngineSecretsRepository($this->tempFile);

        self::assertSame('my-salt', $repository->getSalt());
    }

    public function testThrowsWhenFileContainsInvalidJson(): void
    {
        file_put_contents($this->tempFile, 'not-valid-json');

        $repository = new FsEngineSecretsRepository($this->tempFile);

        $this->expectException(EngineSecretsUnavailableException::class);
        $repository->getAppSecret();
    }

    public function testThrowsWhenAppSecretKeyIsMissing(): void
    {
        file_put_contents($this->tempFile, json_encode(['salt' => 'my-salt']));

        $repository = new FsEngineSecretsRepository($this->tempFile);

        $this->expectException(EngineSecretsUnavailableException::class);
        $repository->getAppSecret();
    }

    public function testThrowsWhenSaltKeyIsMissing(): void
    {
        file_put_contents($this->tempFile, json_encode(['app_secret' => 'my-app-secret']));

        $repository = new FsEngineSecretsRepository($this->tempFile);

        $this->expectException(EngineSecretsUnavailableException::class);
        $repository->getSalt();
    }

    public function testThrowsWhenAppSecretIsNotAString(): void
    {
        file_put_contents($this->tempFile, json_encode(['app_secret' => 42, 'salt' => 'my-salt']));

        $repository = new FsEngineSecretsRepository($this->tempFile);

        $this->expectException(EngineSecretsUnavailableException::class);
        $repository->getAppSecret();
    }

    public function testThrowsWhenFileContainsJsonArray(): void
    {
        file_put_contents($this->tempFile, json_encode(['my-app-secret', 'my-salt']));

        $repository = new FsEngineSecretsRepository($this->tempFile);

        $this->expectException(EngineSecretsUnavailableException::class);
        $repository->getAppSecret();
    }

    public function testThrowsWithoutLeakingPathWhenFileDoesNotExist(): void
    {
        $missingPath = $this->tempFile . '-does-not-exist';
        $repository = new FsEngineSecretsRepository($missingPath);

        try {
            $repository->getAppSecret();
            self::fail('Expected EngineSecretsUnavailableException to be thrown.');
        } catch (EngineSecretsUnavailableException $exception) {
            self::assertStringNotContainsString($missingPath, $exception->getMessage());
        }
    }
}
