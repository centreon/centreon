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

namespace Core\GraphTemplate\Application\UseCase\FindGraphTemplates;

class GraphTemplateDto
{
        public int $id = 0;

        public string $name = '';

        public string $verticalAxisLabel = '';

        public int $width = 0;

        public int $height = 0;

        public int $base = 0;

        public ?float $gridLowerLimit = null;

        public ?float $gridUpperLimit = null;

        public bool $isUpperLimitSizedToMax = false;

        public bool $isGraphScaled = false;

        public bool $isDefaultCentreonTemplate = false;
}
