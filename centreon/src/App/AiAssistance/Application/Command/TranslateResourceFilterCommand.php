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

namespace App\AiAssistance\Application\Command;

use App\Shared\Application\Command\NonTransactionalCommand;
use App\Shared\Domain\Logging\Attribute\Sensitive;
use Webmozart\Assert\Assert;

/**
 * The request is masked in the bus logs: it is free text typed by the user.
 *
 * Not transactional: the usage row of a provider call must survive a later
 * failure, and no transaction may stay open during the provider call.
 */
final readonly class TranslateResourceFilterCommand implements NonTransactionalCommand
{
    public function __construct(
        #[Sensitive]
        public string $prompt,
        /** The search expression currently applied to the listing, for scope context. */
        #[Sensitive]
        public ?string $currentFilter = null,
    ) {
        Assert::lengthBetween($prompt, 1, 2000);
        if ($currentFilter !== null) {
            Assert::maxLength($currentFilter, 2000);
        }
    }
}
