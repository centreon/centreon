<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\HostTemplate\Application\UseCase\PartialUpdateHostTemplate;

use Core\Common\Application\Type\NoValue;

final class PartialUpdateHostTemplateRequest
{
    /**
     * @param NoValue|array<array{name:string,value:string|null,is_password:bool,description:null|string}> $macros
     * @param NoValue|int[] $categories
     * @param NoValue|int[] $templates
     */
    public function __construct(
        public NoValue|array $macros = new NoValue(),
        public NoValue|array $categories = new NoValue(),
        public NoValue|array $templates = new NoValue(),
    ) {
    }
}
