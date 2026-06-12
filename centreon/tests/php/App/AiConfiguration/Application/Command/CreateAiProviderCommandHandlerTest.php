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

use App\AiConfiguration\Application\Command\CreateAiProviderCommand;
use App\AiConfiguration\Application\Command\CreateAiProviderCommandHandler;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ApiToken;
use App\AiConfiguration\Domain\Aggregate\AiProvider\EndpointUrl;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use App\AiConfiguration\Domain\Event\AiProviderCreated;
use App\AiConfiguration\Domain\Exception\AiProviderAlreadyExistsException;
use PHPUnit\Framework\TestCase;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeAiProviderRepository;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeAiProviderTokenStorage;
use Tests\App\Shared\Double\EventBusSpy;

final class CreateAiProviderCommandHandlerTest extends TestCase
{
    private FakeAiProviderRepository $repository;

    private FakeAiProviderTokenStorage $tokenStorage;

    private EventBusSpy $eventBus;

    private CreateAiProviderCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new FakeAiProviderRepository();
        $this->tokenStorage = new FakeAiProviderTokenStorage();
        $this->eventBus = new EventBusSpy();
        $this->handler = new CreateAiProviderCommandHandler($this->repository, $this->tokenStorage, $this->eventBus);
    }

    public function testCreateAiProvider(): void
    {
        ($this->handler)($this->createCommand('Anthropic production'));

        self::assertNotNull($this->repository->findOneByName(new AiProviderName('Anthropic production')));
    }

    public function testFirstCreatedProviderBecomesDefault(): void
    {
        $aiProvider = ($this->handler)($this->createCommand('Anthropic production', isActivated: false));

        self::assertTrue($aiProvider->isActivated);
    }

    public function testSecondProviderDoesNotBecomeDefault(): void
    {
        ($this->handler)($this->createCommand('Anthropic production'));
        $second = ($this->handler)($this->createCommand('Mistral fallback', isActivated: false));

        self::assertFalse($second->isActivated);
    }

    public function testCreatingDefaultProviderMovesTheDefaultFlag(): void
    {
        $first = ($this->handler)($this->createCommand('Anthropic production'));
        $second = ($this->handler)($this->createCommand('Mistral fallback', isActivated: true));

        self::assertTrue($second->isActivated);
        self::assertFalse($this->repository->getById($first->id())->isActivated);
        self::assertSame($second->id()->value, $this->repository->findActivated()?->id()->value);
    }

    public function testCannotCreateProviderWithSameName(): void
    {
        ($this->handler)($this->createCommand('Anthropic production'));

        $this->expectException(AiProviderAlreadyExistsException::class);

        ($this->handler)($this->createCommand('Anthropic production'));
    }

    public function testTokenGoesThroughTokenStorage(): void
    {
        $aiProvider = ($this->handler)($this->createCommand('Anthropic production'));

        self::assertSame(['a-token'], $this->tokenStorage->persistedTokens);
        self::assertSame(FakeAiProviderTokenStorage::STORED_PREFIX . 'a-token', $aiProvider->apiToken->value);
    }

    public function testDispatchCreatedEvent(): void
    {
        ($this->handler)($this->createCommand('Anthropic production'));

        self::assertTrue($this->eventBus->shouldHaveDispatched(AiProviderCreated::class));
    }

    private function createCommand(string $name, bool $isActivated = false): CreateAiProviderCommand
    {
        return new CreateAiProviderCommand(
            name: new AiProviderName($name),
            type: AiProviderTypeEnum::Anthropic,
            apiToken: new ApiToken('a-token'),
            endpointUrl: new EndpointUrl('https://api.anthropic.com'),
            defaultModel: new ModelName('claude-sonnet-4-6'),
            fastModel: null,
            description: null,
            isActivated: $isActivated,
            creatorId: 1,
        );
    }
}
