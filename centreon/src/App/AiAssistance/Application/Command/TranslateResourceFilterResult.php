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
        /**
         * What the user wants: 'filter' (list a set), 'explain' (analyse), or
         * 'unsupported' (the request is unrelated to the monitored resources).
         */
        public string $intent,
        /**
         * For 'filter': the search expression, empty when none could be derived
         * (including when the model output stayed invalid). For 'explain': with
         * scope 'query', the search expression selecting the set to explain;
         * with scope 'current', the model is asked for an empty one and it is
         * to be ignored. Empty for 'unsupported'.
         */
        public string $query,
        /** For 'explain': 'current' (the displayed set) or 'query'. Null for 'filter' and 'unsupported'. */
        public ?string $scope = null,
        /**
         * Why the model output was given up on, still invalid after the
         * correction round-trip (the result is then an empty filter); null
         * otherwise. For the logs, not for the user.
         */
        public ?string $invalidOutputReason = null,
    ) {
    }
}
