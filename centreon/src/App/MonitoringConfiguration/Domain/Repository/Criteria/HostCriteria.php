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

namespace App\MonitoringConfiguration\Domain\Repository\Criteria;

use App\Shared\Domain\Repository\PaginableCriteria;
use App\Shared\Domain\Repository\PaginableCriteriaTrait;
use Webmozart\Assert\Assert;

final class HostCriteria implements PaginableCriteria
{
    use PaginableCriteriaTrait;
    use SingleNameFilterTrait;
    use ViewerScopedCriteriaTrait;

    private ?int $templateId = null;

    private ?int $groupId = null;

    private ?int $pollerId = null;

    private ?bool $activated = null;

    public function withTemplateId(int $templateId): self
    {
        Assert::positiveInteger($templateId);

        $new = clone $this;
        $new->templateId = $templateId;

        return $new;
    }

    public function withGroupId(int $groupId): self
    {
        Assert::positiveInteger($groupId);

        $new = clone $this;
        $new->groupId = $groupId;

        return $new;
    }

    public function withPollerId(int $pollerId): self
    {
        Assert::positiveInteger($pollerId);

        $new = clone $this;
        $new->pollerId = $pollerId;

        return $new;
    }

    public function withActivated(bool $activated): self
    {
        $new = clone $this;
        $new->activated = $activated;

        return $new;
    }

    public function getTemplateId(): ?int
    {
        return $this->templateId;
    }

    public function getGroupId(): ?int
    {
        return $this->groupId;
    }

    public function getPollerId(): ?int
    {
        return $this->pollerId;
    }

    public function getActivated(): ?bool
    {
        return $this->activated;
    }
}
