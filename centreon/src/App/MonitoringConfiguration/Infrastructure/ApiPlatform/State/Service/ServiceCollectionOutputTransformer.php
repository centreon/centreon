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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service;

use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceCollectionOutput;
use App\Shared\Infrastructure\TransformerInterface;

/**
 * @implements TransformerInterface<Service, ServiceCollectionOutput>
 */
final readonly class ServiceCollectionOutputTransformer implements TransformerInterface
{
    /**
     * The service's own scalars only: what it shows from its host and its templates is set by the provider.
     */
    public function transform(mixed $from): ServiceCollectionOutput
    {
        return new ServiceCollectionOutput(
            id: $from->id()->value,
            name: $from->name->value,
            activated: $from->activated,
        );
    }
}
