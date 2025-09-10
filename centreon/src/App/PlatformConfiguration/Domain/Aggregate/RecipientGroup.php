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

namespace App\PlatformConfiguration\Domain\Aggregate;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Aggregate\Collection;

final class RecipientGroup extends AggregateRoot
{
    /**
     * @param Collection<Recipient> $recipients
     */
    public function __construct(
        ?RecipientGroupId $id,
        public readonly RecipientGroupName $name,
        public readonly Collection $recipients,
    ) {
        parent::__construct($id);
    }

    public function addRecipient(Recipient $recipient): void
    {
        if ($this->recipients->contains($recipient)) {
            return;
        }

        $this->recipients->add($recipient);
        $recipient->addGroup($this);
    }
}
