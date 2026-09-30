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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host;

use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\DataProcessingOutputTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\HostCheckOptionsOutputTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\HostExtendedInformationsOutputTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\HostIconOutputTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\HostMacroTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\HostNotificationsTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\HostResourceTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\HostSchedulingOptionsOutputTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Media\MediaUrlGenerator;

/**
 * IS_CLOUD_PLATFORM is compiled into the container and cannot be flipped per test through the env.
 * This builds a HostResourceTransformer whose Cloud-sensitive parts see the desired platform, for a
 * test to wire into the processor or provider it swaps in the container.
 */
final class HostResourceTransformerFactory
{
    public static function create(bool $isCloudPlatform, MediaUrlGenerator $mediaUrlGenerator): HostResourceTransformer
    {
        return new HostResourceTransformer(
            new DataProcessingOutputTransformer($isCloudPlatform),
            new HostExtendedInformationsOutputTransformer(new HostIconOutputTransformer($mediaUrlGenerator)),
            new HostSchedulingOptionsOutputTransformer($isCloudPlatform),
            new HostCheckOptionsOutputTransformer(new HostMacroTransformer()),
            new HostNotificationsTransformer($isCloudPlatform),
        );
    }
}
