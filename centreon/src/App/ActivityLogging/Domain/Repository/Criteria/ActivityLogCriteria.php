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

namespace App\ActivityLogging\Domain\Repository\Criteria;

use App\ActivityLogging\Domain\Aggregate\ActionEnum;
use App\ActivityLogging\Domain\Aggregate\TargetTypeEnum;
use App\Shared\Domain\Repository\PaginableCriteria;
use App\Shared\Domain\Repository\PaginableCriteriaTrait;
use Webmozart\Assert\Assert;

/**
 * Filters of the activity log listing. The sort is fixed: most recent first.
 */
final class ActivityLogCriteria implements PaginableCriteria
{
    use PaginableCriteriaTrait;

    private ?string $targetName = null;

    /** @var list<int> */
    private array $actorIds = [];

    /** @var list<string> */
    private array $tokenNames = [];

    /** @var list<TargetTypeEnum> */
    private array $targetTypes = [];

    private ?int $targetId = null;

    /** @var list<ActionEnum> */
    private array $actions = [];

    private ?\DateTimeImmutable $performedFrom = null;

    private ?\DateTimeImmutable $performedTo = null;

    private bool $isOldestFirst = false;

    /**
     * Partial match on the target name.
     */
    public function withTargetName(string $targetName): self
    {
        Assert::notEmpty($targetName);

        $new = clone $this;
        $new->targetName = $targetName;

        return $new;
    }

    public function withActorId(int $actorId): self
    {
        Assert::positiveInteger($actorId);

        $new = clone $this;
        $new->actorIds = array_values(array_unique([...$this->actorIds, $actorId]));

        return $new;
    }

    /**
     * Exact match on the name of the API token the change was made with.
     */
    public function withTokenName(string $tokenName): self
    {
        Assert::notEmpty($tokenName);

        $new = clone $this;
        $new->tokenNames = array_values(array_unique([...$this->tokenNames, $tokenName]));

        return $new;
    }

    public function withTargetType(TargetTypeEnum $targetType): self
    {
        $new = clone $this;
        if (! in_array($targetType, $this->targetTypes, true)) {
            $new->targetTypes[] = $targetType;
        }

        return $new;
    }

    public function withTargetId(int $targetId): self
    {
        Assert::positiveInteger($targetId);

        $new = clone $this;
        $new->targetId = $targetId;

        return $new;
    }

    public function withAction(ActionEnum $action): self
    {
        $new = clone $this;
        if (! in_array($action, $this->actions, true)) {
            $new->actions[] = $action;
        }

        return $new;
    }

    /**
     * Both bounds are inclusive.
     */
    public function withPerformedBetween(?\DateTimeImmutable $performedFrom, ?\DateTimeImmutable $performedTo): self
    {
        $new = clone $this;
        $new->performedFrom = $performedFrom;
        $new->performedTo = $performedTo;

        return $new;
    }

    /**
     * The most recent logs come first unless asked otherwise.
     */
    public function withOldestFirst(bool $isOldestFirst = true): self
    {
        $new = clone $this;
        $new->isOldestFirst = $isOldestFirst;

        return $new;
    }

    public function isOldestFirst(): bool
    {
        return $this->isOldestFirst;
    }

    public function getTargetName(): ?string
    {
        return $this->targetName;
    }

    /**
     * @return list<int>
     */
    public function getActorIds(): array
    {
        return $this->actorIds;
    }

    /**
     * @return list<string>
     */
    public function getTokenNames(): array
    {
        return $this->tokenNames;
    }

    /**
     * @return list<TargetTypeEnum>
     */
    public function getTargetTypes(): array
    {
        return $this->targetTypes;
    }

    public function getTargetId(): ?int
    {
        return $this->targetId;
    }

    /**
     * @return list<ActionEnum>
     */
    public function getActions(): array
    {
        return $this->actions;
    }

    public function getPerformedFrom(): ?\DateTimeImmutable
    {
        return $this->performedFrom;
    }

    public function getPerformedTo(): ?\DateTimeImmutable
    {
        return $this->performedTo;
    }
}
