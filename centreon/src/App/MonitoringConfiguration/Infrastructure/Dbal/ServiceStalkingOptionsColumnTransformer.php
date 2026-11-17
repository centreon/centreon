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

namespace App\MonitoringConfiguration\Infrastructure\Dbal;

use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceStalkingOptionEnum;
use App\Shared\Infrastructure\TransformerInterface;

/**
 * Write-side counterpart of {@see ServiceTransformer} for the stalking options. An empty list writes NULL, as
 * legacy does.
 *
 * @implements TransformerInterface<list<ServiceStalkingOptionEnum>, ?string>
 */
final readonly class ServiceStalkingOptionsColumnTransformer implements TransformerInterface
{
    public function transform(mixed $from): ?string
    {
        if ($from === []) {
            return null;
        }

        return implode(',', array_map(
            static fn (ServiceStalkingOptionEnum $option): string => match ($option) {
                ServiceStalkingOptionEnum::Ok => 'o',
                ServiceStalkingOptionEnum::Warning => 'w',
                ServiceStalkingOptionEnum::Unknown => 'u',
                ServiceStalkingOptionEnum::Critical => 'c',
            },
            $from,
        ));
    }
}
