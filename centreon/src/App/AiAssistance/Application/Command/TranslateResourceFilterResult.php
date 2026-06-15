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

final readonly class TranslateResourceFilterResult
{
    public function __construct(
        /** What the user wants: 'filter' (list a set) or 'explain' (analyse). */
        public string $intent,
        /**
         * For 'filter': the search expression (empty when none could be derived).
         * For 'explain' with scope 'query': the search expression selecting the
         * set to explain. Empty otherwise.
         */
        public string $query,
        /** For 'explain': 'current' (the displayed set) or 'query'. Null for 'filter'. */
        public ?string $scope = null,
    ) {
    }
}
