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
use App\Shared\Domain\Repository\PaginableCriteria;
use App\Shared\Domain\Repository\PaginableCriteriaTrait;
use Webmozart\Assert\Assert;

final class HostSeverityCriteria implements PaginableCriteria
{
    use PaginableCriteriaTrait;

    private ?string $name = null;

    private ?UserId $viewerId = null;

    public function withName(string $name): self
    {
        // notEmpty() relies on empty(), which would wrongly reject a legitimate name of "0"
        Assert::stringNotEmpty($name);

        $new = clone $this;
        $new->name = $name;

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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getViewerId(): ?UserId
    {
        return $this->viewerId;
    }
}
