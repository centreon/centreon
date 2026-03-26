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

require_once __DIR__ . '/../../../../bootstrap.php';

use Core\Common\Infrastructure\FeatureFlags;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv('/usr/share/centreon/.env');
$isCloudPlatform = false;
if (array_key_exists('IS_CLOUD_PLATFORM', $_ENV) && $_ENV['IS_CLOUD_PLATFORM']) {
    $isCloudPlatform = true;
}
$featuresFileContent = file_get_contents(__DIR__ . '/../../../../config/features.json');
$featureFlagManager = new FeatureFlags($isCloudPlatform, $featuresFileContent);

echo json_encode($featureFlagManager->getAll());
