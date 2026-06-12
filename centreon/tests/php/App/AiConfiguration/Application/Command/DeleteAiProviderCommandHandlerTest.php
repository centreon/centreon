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

namespace Tests\App\AiConfiguration\Application\Command;

use App\AiConfiguration\Application\Command\DeleteAiProviderCommand;
use App\AiConfiguration\Application\Command\DeleteAiProviderCommandHandler;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProvider;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ApiToken;
use App\AiConfiguration\Domain\Aggregate\AiProvider\EndpointUrl;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use App\AiConfiguration\Domain\Event\AiProviderDeleted;
use App\AiConfiguration\Domain\Exception\AiProviderNotFoundException;
use PHPUnit\Framework\TestCase;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeAiProviderRepository;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeAiProviderTokenStorage;
use Tests\App\Shared\Double\EventBusSpy;

final class DeleteAiProviderCommandHandlerTest extends TestCase
{
    private FakeAiProviderRepository $repository;

    private FakeAiProviderTokenStorage $tokenStorage;

    private EventBusSpy $eventBus;

    private DeleteAiProviderCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new FakeAiProviderRepository();
        $this->tokenStorage = new FakeAiProviderTokenStorage();
        $this->eventBus = new EventBusSpy();
        $this->handler = new DeleteAiProviderCommandHandler($this->repository, $this->tokenStorage, $this->eventBus);
    }

    public function testDeleteAiProvider(): void
    {
        $aiProvider = $this->givenAiProvider('Anthropic production');

        ($this->handler)(new DeleteAiProviderCommand($aiProvider->id(), deletedBy: 1));

        self::assertNull($this->repository->findOneByName(new AiProviderName('Anthropic production')));
    }

    public function testDeleteUnknownProviderThrows(): void
    {
        $aiProvider = $this->givenAiProvider('Anthropic production');
        $this->repository->delete($aiProvider);

        $this->expectException(AiProviderNotFoundException::class);

        ($this->handler)(new DeleteAiProviderCommand($aiProvider->id(), deletedBy: 1));
    }

    public function testDeletingTheActivatedProviderLeavesNoneActivated(): void
    {
        $activated = $this->givenAiProvider('Anthropic production', isActivated: true);
        $this->givenAiProvider('Mistral fallback');

        ($this->handler)(new DeleteAiProviderCommand($activated->id(), deletedBy: 1));

        self::assertSame(1, $this->repository->countAll());
        self::assertNull($this->repository->findActivated());
    }

    public function testTokenIsRemovedFromStorage(): void
    {
        $aiProvider = $this->givenAiProvider('Anthropic production');

        ($this->handler)(new DeleteAiProviderCommand($aiProvider->id(), deletedBy: 1));

        self::assertSame(['initial-token'], $this->tokenStorage->removedTokens);
    }

    public function testDispatchDeletedEvent(): void
    {
        $aiProvider = $this->givenAiProvider('Anthropic production');

        ($this->handler)(new DeleteAiProviderCommand($aiProvider->id(), deletedBy: 1));

        self::assertTrue($this->eventBus->shouldHaveDispatched(AiProviderDeleted::class));
    }

    private function givenAiProvider(string $name, bool $isActivated = false): AiProvider
    {
        $aiProvider = new AiProvider(
            id: null,
            name: new AiProviderName($name),
            type: AiProviderTypeEnum::Anthropic,
            endpointUrl: new EndpointUrl('https://api.anthropic.com'),
            apiToken: new ApiToken('initial-token'),
            defaultModel: new ModelName('claude-sonnet-4-6'),
            fastModel: null,
            description: null,
            isActivated: $isActivated,
        );
        $this->repository->add($aiProvider);

        return $aiProvider;
    }
}
