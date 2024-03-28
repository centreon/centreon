<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\Platform\Application\UseCase\FindFeatures;

use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Common\Application\FeatureFlagsInterface;

final class FindFeatures
{
    use LoggerTrait;

    public function __construct(private readonly FeatureFlagsInterface $featureFlags)
    {
    }

    public function __invoke(FindFeaturesPresenterInterface $presenter): void
    {
        try {
            $response = new FindFeaturesResponse(
                $this->featureFlags->isCloudPlatform(),
                $this->featureFlags->getAll(),
            );
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $response = new ErrorResponse('Error while searching for feature flags');
        }

        $presenter->presentResponse($response);
    }
}
