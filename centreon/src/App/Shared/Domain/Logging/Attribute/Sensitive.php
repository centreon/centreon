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
 * Marks a property as sensitive — the platform payload sanitiser (the
 * `bus` channel via `LoggingMiddleware`, every other channel via
 * `SanitizingProcessor`) replaces the value with `***` whenever the
 * containing class flows through the logging pipeline.
 *
 * `#[Sensitive]` is the **single source of truth** for masking: a
 * property that does not carry the attribute is logged in clear. Any
 * class that holds a secret — Command, Query, Domain aggregate, value
 * object, request DTO — must annotate the relevant property.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class Sensitive
{
}
