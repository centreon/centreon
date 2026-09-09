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

use App\Security\Domain\Aggregate\UserId;
use Webmozart\Assert\Assert;

final class HostCriteria
{
    private ?int $page = null;

    private ?int $itemsPerPage = null;

    private ?string $name = null;

    private ?int $templateId = null;

    private ?int $groupId = null;

    private ?int $pollerId = null;

    private ?bool $activated = null;

    private ?UserId $viewerId = null;

    public function withPagination(int $page, int $itemsPerPage): self
    {
        Assert::positiveInteger($page);
        Assert::positiveInteger($itemsPerPage);

        $new = clone $this;
        $new->page = $page;
        $new->itemsPerPage = $itemsPerPage;

        return $new;
    }

    public function withName(string $name): self
    {
        Assert::stringNotEmpty($name);

        $new = clone $this;
        $new->name = $name;

        return $new;
    }

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

    /**
     * @param UserId|null $viewerId the user to scope results for, or null when no ACL restriction applies (e.g. an admin)
     */
    public function withViewerId(?UserId $viewerId): self
    {
        $new = clone $this;
        $new->viewerId = $viewerId;

        return $new;
    }

    public function getPage(): ?int
    {
        return $this->page;
    }

    public function getItemsPerPage(): ?int
    {
        return $this->itemsPerPage;
    }

    public function getName(): ?string
    {
        return $this->name;
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

    public function getViewerId(): ?UserId
    {
        return $this->viewerId;
    }
}
