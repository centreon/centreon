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

final class ContactGroupCriteria
{
    public const OPERATOR_LIKE = 'lk';
    public const ALLOWED_OPERATORS = [self::OPERATOR_LIKE];

    private ?int $page = null;

    private ?int $itemsPerPage = null;

    /** @var list<string> */
    private array $names = [];

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
        // stringNotEmpty (not notEmpty) so a legitimate name of "0" is not wrongly rejected.
        Assert::stringNotEmpty($name);

        $names = $this->names;
        $names[] = $name;

        $new = clone $this;
        $new->names = array_values(array_unique($names));

        return $new;
    }

    /**
     * @param UserId $viewerId the user to scope results for; omitting the call means no ACL restriction (e.g. an admin)
     */
    public function withViewerId(UserId $viewerId): self
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

    /**
     * @return list<string>
     */
    public function getNames(): array
    {
        return $this->names;
    }

    public function getViewerId(): ?UserId
    {
        return $this->viewerId;
    }
}
