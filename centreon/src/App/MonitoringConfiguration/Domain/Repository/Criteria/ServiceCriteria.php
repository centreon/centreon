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
use App\Shared\Domain\Repository\SortableCriteria;
use App\Shared\Domain\Repository\SortableCriteriaTrait;
use Webmozart\Assert\Assert;

final class ServiceCriteria implements PaginableCriteria, SortableCriteria
{
    use PaginableCriteriaTrait;
    use SortableCriteriaTrait;
    use ActorScopedCriteriaTrait;

    private ?string $hostName = null;

    private ?string $name = null;

    private ?int $templateId = null;

    private ?bool $activated = null;

    private ?bool $hostActivated = null;

    public function withHostName(string $hostName): self
    {
        // stringNotEmpty (not notEmpty) so a legitimate name of "0" is not wrongly rejected.
        Assert::stringNotEmpty($hostName);

        $new = clone $this;
        $new->hostName = $hostName;

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

    public function withActivated(bool $activated): self
    {
        $new = clone $this;
        $new->activated = $activated;

        return $new;
    }

    public function withHostActivated(bool $hostActivated): self
    {
        $new = clone $this;
        $new->hostActivated = $hostActivated;

        return $new;
    }

    public function getHostName(): ?string
    {
        return $this->hostName;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getTemplateId(): ?int
    {
        return $this->templateId;
    }

    public function getActivated(): ?bool
    {
        return $this->activated;
    }

    public function getHostActivated(): ?bool
    {
        return $this->hostActivated;
    }

    /**
     * Sortable fields exposed by the API, mapped to their column qualified by its table alias, since a listed
     * item spans the `host` (`h`) and `service` (`s`) tables.
     *
     * @return array<string, string>
     */
    public function getFieldMapping(): array
    {
        return [
            'host_name' => 'h.host_name',
            'name' => 's.service_description',
        ];
    }
}
