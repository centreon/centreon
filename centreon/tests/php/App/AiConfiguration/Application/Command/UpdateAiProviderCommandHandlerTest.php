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

use App\AiConfiguration\Application\Command\UpdateAiProviderCommand;
use App\AiConfiguration\Application\Command\UpdateAiProviderCommandHandler;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProvider;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ApiToken;
use App\AiConfiguration\Domain\Aggregate\AiProvider\EndpointUrl;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use App\AiConfiguration\Domain\Event\AiProviderUpdated;
use App\AiConfiguration\Domain\Exception\AiProviderAlreadyExistsException;
use App\AiConfiguration\Domain\Exception\AiProviderNotFoundException;
use PHPUnit\Framework\TestCase;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeAiProviderRepository;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeAiProviderTokenStorage;
use Tests\App\Shared\Double\EventBusSpy;

final class UpdateAiProviderCommandHandlerTest extends TestCase
{
    private FakeAiProviderRepository $repository;

    private FakeAiProviderTokenStorage $tokenStorage;

    private EventBusSpy $eventBus;

    private UpdateAiProviderCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new FakeAiProviderRepository();
        $this->tokenStorage = new FakeAiProviderTokenStorage();
        $this->eventBus = new EventBusSpy();
        $this->handler = new UpdateAiProviderCommandHandler($this->repository, $this->tokenStorage, $this->eventBus);
    }

    public function testRenameAiProvider(): void
    {
        $aiProvider = $this->givenAiProvider('Anthropic production', isActivated: true);

        ($this->handler)($this->updateCommand($aiProvider, name: 'Anthropic renamed'));

        self::assertNotNull($this->repository->findOneByName(new AiProviderName('Anthropic renamed')));
    }

    public function testUpdateUnknownProviderThrows(): void
    {
        $aiProvider = $this->givenAiProvider('Anthropic production', isActivated: true);
        $command = $this->updateCommand($aiProvider);
        $this->repository->delete($aiProvider);

        $this->expectException(AiProviderNotFoundException::class);

        ($this->handler)($command);
    }

    public function testCannotRenameToAnExistingName(): void
    {
        $this->givenAiProvider('Anthropic production', isActivated: true);
        $aiProvider = $this->givenAiProvider('Mistral fallback');

        $this->expectException(AiProviderAlreadyExistsException::class);

        ($this->handler)($this->updateCommand($aiProvider, name: 'Anthropic production'));
    }

    public function testKeepingOwnNameIsAllowed(): void
    {
        $aiProvider = $this->givenAiProvider('Anthropic production', isActivated: true);

        $updated = ($this->handler)($this->updateCommand($aiProvider));

        self::assertSame('Anthropic production', $updated->name->value);
    }

    public function testDisablingTheActivatedProviderLeavesNoneActivated(): void
    {
        $aiProvider = $this->givenAiProvider('Anthropic production', isActivated: true);

        ($this->handler)($this->updateCommand($aiProvider, isActivated: false));

        self::assertFalse($this->repository->getById($aiProvider->id())->isActivated);
        self::assertNull($this->repository->findActivated());
    }

    public function testEnablingAProviderDisablesTheCurrentlyActivatedOne(): void
    {
        $first = $this->givenAiProvider('Anthropic production', isActivated: true);
        $second = $this->givenAiProvider('Mistral fallback');

        ($this->handler)($this->updateCommand($second, isActivated: true));

        self::assertTrue($this->repository->getById($second->id())->isActivated);
        self::assertFalse($this->repository->getById($first->id())->isActivated);
    }

    public function testNullTokenKeepsCurrentToken(): void
    {
        $aiProvider = $this->givenAiProvider('Anthropic production', isActivated: true);

        $updated = ($this->handler)($this->updateCommand($aiProvider, apiToken: null));

        self::assertSame('initial-token', $updated->apiToken->value);
        self::assertSame([], $this->tokenStorage->persistedTokens);
    }

    public function testProvidedTokenGoesThroughTokenStorage(): void
    {
        $aiProvider = $this->givenAiProvider('Anthropic production', isActivated: true);

        $updated = ($this->handler)($this->updateCommand($aiProvider, apiToken: 'new-token'));

        self::assertSame(['new-token'], $this->tokenStorage->persistedTokens);
        self::assertSame(FakeAiProviderTokenStorage::STORED_PREFIX . 'new-token', $updated->apiToken->value);
    }

    public function testDispatchUpdatedEvent(): void
    {
        $aiProvider = $this->givenAiProvider('Anthropic production', isActivated: true);

        ($this->handler)($this->updateCommand($aiProvider));

        self::assertTrue($this->eventBus->shouldHaveDispatched(AiProviderUpdated::class));
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

    private function updateCommand(
        AiProvider $aiProvider,
        ?string $name = null,
        ?string $apiToken = null,
        ?bool $isActivated = null,
    ): UpdateAiProviderCommand {
        return new UpdateAiProviderCommand(
            id: $aiProvider->id(),
            name: new AiProviderName($name ?? $aiProvider->name->value),
            endpointUrl: $aiProvider->endpointUrl,
            apiToken: $apiToken !== null ? new ApiToken($apiToken) : null,
            defaultModel: $aiProvider->defaultModel,
            fastModel: $aiProvider->fastModel,
            description: $aiProvider->description,
            isActivated: $isActivated ?? $aiProvider->isActivated,
            updatedBy: 1,
        );
    }
}
