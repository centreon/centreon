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
use App\AiConfiguration\Domain\Aggregate\AiProvider\ReasoningModeEnum;
use App\AiConfiguration\Domain\Aggregate\AiProvider\StoredApiToken;
use App\AiConfiguration\Domain\Event\AiProviderUpdated;
use App\AiConfiguration\Domain\Exception\AiProviderActivationConflictException;
use App\AiConfiguration\Domain\Exception\AiProviderAlreadyExistsException;
use App\AiConfiguration\Domain\Exception\AiProviderCanNotBeUpdatedException;
use App\AiConfiguration\Domain\Exception\AiProviderNotFoundException;
use App\AiConfiguration\Domain\Exception\InvalidAiProviderConfigurationException;
use App\AiConfiguration\Domain\Exception\VaultWriteFailedException;
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
        // the token in use is kept, not discarded
        self::assertSame([], $this->tokenStorage->discardedTokens);
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

        self::assertSame('Anthropic production', $updated->name()->value);
    }

    public function testDisablingTheActivatedProviderLeavesNoneActivated(): void
    {
        $aiProvider = $this->givenAiProvider('Anthropic production', isActivated: true);

        ($this->handler)($this->updateCommand($aiProvider, isActivated: false));

        self::assertFalse($this->repository->getById($aiProvider->id())->isActivated());
        self::assertNull($this->repository->findActivated());
    }

    public function testEnablingAProviderDisablesTheCurrentlyActivatedOne(): void
    {
        $first = $this->givenAiProvider('Anthropic production', isActivated: true);
        $second = $this->givenAiProvider('Mistral fallback');

        ($this->handler)($this->updateCommand($second, isActivated: true));

        self::assertTrue($this->repository->getById($second->id())->isActivated());
        self::assertFalse($this->repository->getById($first->id())->isActivated());
    }

    public function testTheDisabledProviderIsReportedAsUpdated(): void
    {
        $first = $this->givenAiProvider('Anthropic production', isActivated: true);
        $second = $this->givenAiProvider('Mistral fallback');

        ($this->handler)($this->updateCommand($second, isActivated: true));

        $updatedStates = array_map(
            static function (AiProviderUpdated $event): array {
                $aiProvider = $event->aggregate;
                \assert($aiProvider instanceof AiProvider);

                return [$aiProvider->id()->value, $aiProvider->isActivated()];
            },
            $this->eventBus->getDispatchedEvents(AiProviderUpdated::class),
        );
        self::assertSame([[$second->id()->value, true], [$first->id()->value, false]], $updatedStates);
    }

    public function testNullTokenKeepsCurrentToken(): void
    {
        $aiProvider = $this->givenAiProvider('Anthropic production', isActivated: true);

        $updated = ($this->handler)($this->updateCommand($aiProvider, apiToken: null));

        self::assertSame('initial-token', $updated->apiToken()?->value);
        self::assertSame([], $this->tokenStorage->persistedTokens);
        // the token in use is kept, not discarded
        self::assertSame([], $this->tokenStorage->discardedTokens);
    }

    public function testProvidedTokenGoesThroughTokenStorage(): void
    {
        $aiProvider = $this->givenAiProvider('Anthropic production', isActivated: true);

        $updated = ($this->handler)($this->updateCommand($aiProvider, apiToken: 'new-token'));

        self::assertSame(['new-token'], $this->tokenStorage->persistedTokens);
        self::assertSame(FakeAiProviderTokenStorage::STORED_PREFIX . 'new-token', $updated->apiToken()?->value);
        // the replaced token is removed once the configuration references the new one
        self::assertSame(['initial-token'], $this->tokenStorage->discardedTokens);
    }

    public function testAFailedSaveRemovesTheNewTokenAndKeepsTheStoredOne(): void
    {
        $aiProvider = $this->givenAiProvider('Anthropic production');
        $this->repository->writeFailure = AiProviderActivationConflictException::anotherProviderEnabled();

        try {
            ($this->handler)($this->updateCommand($aiProvider, apiToken: 'new-token', isActivated: true));
            self::fail('A failed save must abort the update.');
        } catch (AiProviderActivationConflictException) {
        }

        self::assertSame([FakeAiProviderTokenStorage::STORED_PREFIX . 'new-token'], $this->tokenStorage->discardedTokens);
        self::assertSame([], $this->tokenStorage->removedTokens);
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(AiProviderUpdated::class));
    }

    public function testVaultWriteFailureKeepsTheStoredToken(): void
    {
        $aiProvider = $this->givenAiProvider('Anthropic production', isActivated: true);
        $this->tokenStorage->persistThrows = true;

        try {
            ($this->handler)($this->updateCommand($aiProvider, apiToken: 'new-token'));
            self::fail('A failed vault write must abort the update.');
        } catch (VaultWriteFailedException) {
        }

        self::assertSame('initial-token', $this->repository->getById($aiProvider->id())->apiToken()?->value);
        self::assertSame([], $this->tokenStorage->discardedTokens);
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(AiProviderUpdated::class));
    }

    public function testChangingTheEndpointRequiresTheTokenAgainForATypeThatNeedsOne(): void
    {
        $aiProvider = $this->givenAiProvider(
            'Bedrock production',
            isActivated: true,
            type: AiProviderTypeEnum::Bedrock,
            endpointUrl: 'https://bedrock-mantle.us-east-1.api.aws',
        );

        try {
            ($this->handler)($this->updateCommand($aiProvider, apiToken: null, endpointUrl: 'https://bedrock-mantle.eu-west-1.api.aws'));
            self::fail('The stored token must not be kept for another endpoint.');
        } catch (AiProviderCanNotBeUpdatedException) {
        }

        self::assertSame('https://bedrock-mantle.us-east-1.api.aws', $this->repository->getById($aiProvider->id())->endpointUrl()->value);
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(AiProviderUpdated::class));
    }

    public function testTheStoredTokenOfAServerThatMayNeedNoneIsRemovedOnRequest(): void
    {
        $aiProvider = $this->givenAiProvider('Local Ollama', isActivated: true);

        ($this->handler)($this->updateCommand(
            $aiProvider,
            apiToken: null,
            endpointUrl: 'http://ollama.internal:11434',
            removeApiToken: true,
        ));

        $updated = $this->repository->getById($aiProvider->id());
        self::assertSame('http://ollama.internal:11434', $updated->endpointUrl()->value);
        // removed from the vault within the transaction, like a deletion
        self::assertNull($updated->apiToken());
        self::assertSame(['initial-token'], $this->tokenStorage->removedTokens);
        self::assertSame([], $this->tokenStorage->discardedTokens);
    }

    public function testTheTokenCanBeRemovedWithoutChangingTheEndpoint(): void
    {
        $aiProvider = $this->givenAiProvider('Local gateway');

        ($this->handler)($this->updateCommand($aiProvider, removeApiToken: true));

        self::assertNull($this->repository->getById($aiProvider->id())->apiToken());
        self::assertSame(['initial-token'], $this->tokenStorage->removedTokens);
    }

    public function testAVaultFailureOnRemovalSavesNothing(): void
    {
        $aiProvider = $this->givenAiProvider('Local gateway');
        $this->tokenStorage->removeThrows = true;

        $this->expectException(VaultWriteFailedException::class);
        $this->expectExceptionMessage('its API token could not be removed from the vault');

        ($this->handler)($this->updateCommand($aiProvider, removeApiToken: true));
    }

    public function testARefusedRemovalLeavesTheVaultAlone(): void
    {
        $aiProvider = $this->givenAiProvider(
            'Bedrock production',
            type: AiProviderTypeEnum::Bedrock,
            endpointUrl: 'https://bedrock-mantle.us-east-1.api.aws',
        );

        try {
            ($this->handler)($this->updateCommand($aiProvider, removeApiToken: true));
            self::fail('The token of a type that needs one can not be removed.');
        } catch (InvalidAiProviderConfigurationException) {
        }

        self::assertSame('initial-token', $this->repository->getById($aiProvider->id())->apiToken()?->value);
        self::assertSame([], $this->tokenStorage->removedTokens);
        self::assertSame([], $this->tokenStorage->discardedTokens);
    }

    public function testAProviderWithoutATokenCanBeMovedToAnotherEndpoint(): void
    {
        $aiProvider = $this->givenAiProvider('Local Ollama', apiToken: null);

        ($this->handler)($this->updateCommand($aiProvider, endpointUrl: 'http://ollama-b.internal:11434'));

        self::assertSame('http://ollama-b.internal:11434', $this->repository->getById($aiProvider->id())->endpointUrl()->value);
    }

    public function testAProviderWithoutATokenCanBeEdited(): void
    {
        $aiProvider = $this->givenAiProvider('Local Ollama', apiToken: null);

        ($this->handler)($this->updateCommand($aiProvider, name: 'Local Ollama, renamed'));

        $updated = $this->repository->getById($aiProvider->id());
        self::assertNull($updated->apiToken());
        self::assertSame([], $this->tokenStorage->persistedTokens);
        self::assertSame([], $this->tokenStorage->discardedTokens);
    }

    public function testAProviderWithoutATokenCanBeGivenOne(): void
    {
        $aiProvider = $this->givenAiProvider('Local Ollama', apiToken: null);

        ($this->handler)($this->updateCommand($aiProvider, apiToken: 'a-new-token'));

        self::assertSame(['a-new-token'], $this->tokenStorage->persistedTokens);
        self::assertNotNull($this->repository->getById($aiProvider->id())->apiToken());
        self::assertSame([], $this->tokenStorage->discardedTokens);
    }

    public function testChangingTheEndpointWithANewTokenIsAllowed(): void
    {
        $aiProvider = $this->givenAiProvider('Anthropic production', isActivated: true);

        $updated = ($this->handler)(
            $this->updateCommand($aiProvider, apiToken: 'new-token', endpointUrl: 'https://llm.example.com')
        );

        self::assertSame('https://llm.example.com', $updated->endpointUrl()->value);
        self::assertSame(['new-token'], $this->tokenStorage->persistedTokens);
    }

    public function testATrailingSlashDoesNotChangeTheEndpoint(): void
    {
        $aiProvider = $this->givenAiProvider('Anthropic production', isActivated: true);

        $updated = ($this->handler)(
            $this->updateCommand($aiProvider, apiToken: null, endpointUrl: 'https://api.anthropic.com/')
        );

        self::assertSame('initial-token', $updated->apiToken()?->value);
        // the token in use is kept, not discarded
        self::assertSame([], $this->tokenStorage->discardedTokens);
    }

    public function testDispatchUpdatedEvent(): void
    {
        $aiProvider = $this->givenAiProvider('Anthropic production', isActivated: true);

        ($this->handler)($this->updateCommand($aiProvider));

        self::assertTrue($this->eventBus->shouldHaveDispatched(AiProviderUpdated::class));
    }

    public function testTheReasoningSettingsAreUpdated(): void
    {
        $aiProvider = $this->givenAiProvider('Anthropic production', isActivated: true);

        ($this->handler)($this->updateCommand(
            $aiProvider,
            defaultModelReasoning: ReasoningModeEnum::Default,
            fastModelReasoning: ReasoningModeEnum::Minimal,
        ));

        $updated = $this->repository->getById($aiProvider->id());
        self::assertSame(ReasoningModeEnum::Default, $updated->defaultModelReasoning());
        self::assertSame(ReasoningModeEnum::Minimal, $updated->fastModelReasoning());
    }

    private function givenAiProvider(
        string $name,
        bool $isActivated = false,
        // a type accepting any endpoint, to exercise the token reuse rule
        AiProviderTypeEnum $type = AiProviderTypeEnum::OpenAiCompatible,
        string $endpointUrl = 'https://api.anthropic.com',
        ?StoredApiToken $apiToken = new StoredApiToken('initial-token'),
    ): AiProvider {
        $aiProvider = new AiProvider(
            id: null,
            name: new AiProviderName($name),
            type: $type,
            endpointUrl: new EndpointUrl($endpointUrl),
            apiToken: $apiToken,
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
        ?string $endpointUrl = null,
        ?ReasoningModeEnum $defaultModelReasoning = null,
        ?ReasoningModeEnum $fastModelReasoning = null,
        bool $removeApiToken = false,
    ): UpdateAiProviderCommand {
        return new UpdateAiProviderCommand(
            id: $aiProvider->id(),
            name: new AiProviderName($name ?? $aiProvider->name()->value),
            endpointUrl: $endpointUrl !== null ? new EndpointUrl($endpointUrl) : $aiProvider->endpointUrl(),
            apiToken: $apiToken !== null ? new ApiToken($apiToken) : null,
            defaultModel: $aiProvider->defaultModel(),
            fastModel: $aiProvider->fastModel(),
            defaultModelReasoning: $defaultModelReasoning ?? $aiProvider->defaultModelReasoning(),
            fastModelReasoning: $fastModelReasoning ?? $aiProvider->fastModelReasoning(),
            description: $aiProvider->description(),
            isActivated: $isActivated ?? $aiProvider->isActivated(),
            updatedBy: 1,
            removeApiToken: $removeApiToken,
        );
    }
}
