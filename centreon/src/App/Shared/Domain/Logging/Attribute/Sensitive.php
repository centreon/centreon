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

namespace App\Shared\Domain\Logging\Attribute;

/**
 * Marks a value that must be masked (`***`) when the containing object
 * flows through the logging pipeline. Allowed on:
 *
 *  - a **property** — its value is masked;
 *  - a **method** (typically a getter) — the accessor key it exposes is
 *    masked (`getX`/`isX`/`hasX` → `x`, otherwise the raw method name);
 *  - a **class** — every value typed as that class is masked wholesale,
 *    so the sanitiser never descends into it.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
final readonly class Sensitive
{
}
