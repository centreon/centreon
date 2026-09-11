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

use Webmozart\Assert\Assert;

/**
 * Single-value name filtering shared by the listing criteria that filter on one
 * exact name (Host, HostCategory, HostGroup, HostTemplate, Poller).
 */
trait SingleNameFilterTrait
{
    private ?string $name = null;

    public function withName(string $name): self
    {
        // stringNotEmpty() rejects only "", unlike notEmpty()/empty() which would
        // wrongly reject a legitimate name of "0".
        Assert::stringNotEmpty($name);

        $new = clone $this;
        $new->name = $name;

        return $new;
    }

    public function getName(): ?string
    {
        return $this->name;
    }
}
