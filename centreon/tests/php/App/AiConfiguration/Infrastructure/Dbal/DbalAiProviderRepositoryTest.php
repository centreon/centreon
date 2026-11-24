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

namespace Tests\App\AiConfiguration\Infrastructure\Dbal;

use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProvider;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderDescription;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderId;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Domain\Aggregate\AiProvider\Billing;
use App\AiConfiguration\Domain\Aggregate\AiProvider\Currency;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\Pricing;
use App\AiConfiguration\Domain\Aggregate\AiProvider\SpendLimit;
use App\AiConfiguration\Domain\Aggregate\AiProvider\StoredApiToken;
use App\AiConfiguration\Domain\Aggregate\AiProvider\TierPricing;
use App\AiConfiguration\Domain\Exception\AiProviderActivationConflictException;
use App\AiConfiguration\Domain\Exception\AiProviderAlreadyExistsException;
use App\AiConfiguration\Domain\Exception\AiProviderNotFoundException;
use App\AiConfiguration\Domain\Repository\Criteria\AiProviderCriteria;
use App\AiConfiguration\Infrastructure\Dbal\DbalAiProviderRepository;
use App\Shared\Domain\Repository\Pagination;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalAiProviderRepositoryTest extends KernelTestCase
{
    private DbalAiProviderRepository $repository;

    protected function setUp(): void
    {
        /** @var DbalAiProviderRepository $repository */
        $repository = self::getContainer()->get(DbalAiProviderRepository::class);

        $this->repository = $repository;
    }

    public function testAddAndGetById(): void
    {
        $aiProvider = $this->newAiProvider('Anthropic production', isActivated: true);

        $this->repository->add($aiProvider);

        $fetched = $this->repository->getById($aiProvider->id());

        self::assertSame('Anthropic production', $fetched->name()->value);
        self::assertSame(AiProviderTypeEnum::Anthropic, $fetched->type());
        self::assertSame('https://api.anthropic.com', $fetched->endpointUrl()->value);
        self::assertSame('a-token', $fetched->apiToken()->value);
        self::assertSame('claude-sonnet-4-6', $fetched->defaultModel()->value);
        self::assertSame('claude-haiku-4-5', $fetched->fastModel()?->value);
        self::assertSame('a description', $fetched->description()?->value);
        self::assertTrue($fetched->isActivated());
    }

    public function testTheBillingIsReadBackAsSaved(): void
    {
        $billing = new Billing(
            currency: new Currency('EUR'),
            pricing: new Pricing(new TierPricing(3.0, 15.0), new TierPricing(0.8, 4.0)),
            limit: new SpendLimit(daily: 10.5, monthly: 200.0),
        );
        $aiProvider = $this->newAiProvider('Anthropic production', billing: $billing);

        $this->repository->add($aiProvider);

        $fetched = $this->repository->getById($aiProvider->id());
        self::assertEquals($billing, $fetched->billing());
        self::assertEqualsWithDelta(10.5, $fetched->billing()->limit?->daily, 0.0001);
        self::assertEqualsWithDelta(0.8, $fetched->billing()->pricing?->fast?->inputPricePerMillion, 0.0001);
    }

    public function testAnUpdateCanClearTheSpendLimit(): void
    {
        $currency = new Currency('USD');
        $pricing = new Pricing(new TierPricing(3.0, 15.0));
        $aiProvider = $this->newAiProvider(
            'Anthropic production',
            billing: new Billing($currency, $pricing, new SpendLimit(daily: 10.0, monthly: 100.0)),
        );
        $this->repository->add($aiProvider);

        $aiProvider->changeBilling(new Billing($currency, $pricing, null));
        $this->repository->update($aiProvider);

        $fetched = $this->repository->getById($aiProvider->id());
        self::assertNull($fetched->billing()->limit);
        self::assertEquals($pricing, $fetched->billing()->pricing);
        self::assertSame('USD', $fetched->billing()->currency?->value);
    }

    public function testGetByIdNotFound(): void
    {
        $this->expectException(AiProviderNotFoundException::class);

        $this->repository->getById(new AiProviderId(999999));
    }

    public function testFindOneByName(): void
    {
        $this->repository->add($this->newAiProvider('Anthropic production'));

        self::assertNotNull($this->repository->findOneByName(new AiProviderName('Anthropic production')));
        self::assertNull($this->repository->findOneByName(new AiProviderName('unknown')));
    }

    public function testFindDefault(): void
    {
        self::assertNull($this->repository->findActivated());

        $this->repository->add($this->newAiProvider('Anthropic production', isActivated: true));

        self::assertSame('Anthropic production', $this->repository->findActivated()?->name()->value);
    }

    public function testCountAll(): void
    {
        self::assertSame(0, $this->repository->countAll());

        $this->repository->add($this->newAiProvider('Anthropic production'));
        $this->repository->add($this->newAiProvider('Mistral fallback', type: AiProviderTypeEnum::Mistral));

        self::assertSame(2, $this->repository->countAll());
    }

    public function testUpdate(): void
    {
        $aiProvider = $this->newAiProvider('Anthropic production');
        $this->repository->add($aiProvider);

        $aiProvider->rename(new AiProviderName('Anthropic renamed'));
        $aiProvider->retarget($aiProvider->endpointUrl(), new StoredApiToken('new-token'));
        $aiProvider->activate();

        $this->repository->update($aiProvider);

        $fetched = $this->repository->getById($aiProvider->id());
        self::assertSame('Anthropic renamed', $fetched->name()->value);
        self::assertSame('new-token', $fetched->apiToken()->value);
        self::assertTrue($fetched->isActivated());
    }

    public function testASecondActivatedProviderIsAConflict(): void
    {
        // what two concurrent creations would do: the unique key keeps a single enabled provider
        $this->repository->add($this->newAiProvider('Anthropic production', isActivated: true));

        $this->expectException(AiProviderActivationConflictException::class);

        $this->repository->add($this->newAiProvider('Mistral fallback', type: AiProviderTypeEnum::Mistral, isActivated: true));
    }

    public function testActivatingASecondProviderOnUpdateIsAConflict(): void
    {
        $this->repository->add($this->newAiProvider('Anthropic production', isActivated: true));
        $next = $this->newAiProvider('Mistral fallback', type: AiProviderTypeEnum::Mistral);
        $this->repository->add($next);

        $next->activate();

        $this->expectException(AiProviderActivationConflictException::class);

        $this->repository->update($next);
    }

    public function testAConflictingUpdateSavesNothing(): void
    {
        $this->repository->add($this->newAiProvider('Anthropic production', isActivated: true));
        $next = $this->newAiProvider('Mistral fallback', type: AiProviderTypeEnum::Mistral);
        $other = $this->newAiProvider('Mistral backup', type: AiProviderTypeEnum::Mistral);
        $this->repository->add($next);
        $this->repository->add($other);

        // the renamed provider is written first, then the activation conflicts
        $other->rename(new AiProviderName('Mistral renamed'));
        $next->activate();

        try {
            $this->repository->update($next, $other);
            self::fail('Expected an activation conflict.');
        } catch (AiProviderActivationConflictException) {
        }

        self::assertSame('Mistral backup', $this->repository->getById($other->id())->name()->value);
        self::assertSame('Anthropic production', $this->repository->findActivated()?->name()->value);
    }

    public function testADuplicateNameIsAConflict(): void
    {
        $this->repository->add($this->newAiProvider('Anthropic production'));

        $this->expectException(AiProviderAlreadyExistsException::class);

        $this->repository->add($this->newAiProvider('Anthropic production'));
    }

    public function testMovingTheActivationInOneUpdate(): void
    {
        $current = $this->newAiProvider('Anthropic production', isActivated: true);
        $next = $this->newAiProvider('Mistral fallback', type: AiProviderTypeEnum::Mistral);
        $this->repository->add($current);
        $this->repository->add($next);

        $next->activate();
        $current->deactivate();
        // given in the order a handler builds it: the newly enabled provider first
        $this->repository->update($next, $current);

        self::assertSame('Mistral fallback', $this->repository->findActivated()?->name()->value);
    }

    public function testDelete(): void
    {
        $aiProvider = $this->newAiProvider('Anthropic production');
        $this->repository->add($aiProvider);

        $this->repository->delete($aiProvider);

        self::assertSame(0, $this->repository->countAll());
    }

    public function testFindAllWithCriteria(): void
    {
        $this->repository->add($this->newAiProvider('Anthropic production', isActivated: true));
        $this->repository->add($this->newAiProvider('Mistral fallback', type: AiProviderTypeEnum::Mistral));

        $all = $this->repository->findAll(null);
        self::assertCount(2, $all);

        $criteria = new AiProviderCriteria()->withName('mistral', AiProviderCriteria::OPERATOR_LIKE);
        $filtered = $this->repository->findAll($criteria);
        self::assertCount(1, $filtered);

        $criteria = new AiProviderCriteria()->withType(AiProviderTypeEnum::Anthropic->value);
        $filtered = $this->repository->findAll($criteria);
        self::assertCount(1, $filtered);

        $criteria = new AiProviderCriteria()->withIsActivated(true);
        $filtered = $this->repository->findAll($criteria);
        self::assertCount(1, $filtered);

        $criteria = new AiProviderCriteria()->withPagination(new Pagination(1, 1))->withSort('name', 'ASC');
        $paginated = $this->repository->findAll($criteria);
        self::assertCount(1, $paginated);
    }

    private function newAiProvider(
        string $name,
        AiProviderTypeEnum $type = AiProviderTypeEnum::Anthropic,
        bool $isActivated = false,
        Billing $billing = new Billing(),
    ): AiProvider {
        return new AiProvider(
            id: null,
            name: new AiProviderName($name),
            type: $type,
            endpointUrl: $type->resolveEndpoint('https://bedrock-mantle.us-east-1.api.aws'),
            apiToken: new StoredApiToken('a-token'),
            defaultModel: new ModelName('claude-sonnet-4-6'),
            fastModel: new ModelName('claude-haiku-4-5'),
            description: new AiProviderDescription('a description'),
            isActivated: $isActivated,
            billing: $billing,
        );
    }
}
