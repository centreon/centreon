<?php

/*
 * Copyright 2005 - 2019 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

use App\Kernel;
use Security\Interfaces\EncryptionInterface;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;

set_time_limit(0);
require __DIR__ . '/../../../../vendor/autoload.php';

$return = [];
$engineContextPath = '/etc/centreon-engine/engine-context.json';
$kernel = Kernel::createForWeb();
(new Dotenv())->bootEnv('/usr/share/centreon/.env');

/** @var EncryptionInterface $encryption */
$encryption = $kernel->getContainer()->get(EncryptionInterface::class);
$engineContext = [
    'app_secret' => $_ENV['APP_SECRET'],
    'salt' => $encryption->generateRandomString()
];

if (! file_exists($engineContextPath)) {
    $return['msg'] = 'file ' . $engineContextPath . ' does not exists, '
        . 'consider creating it with 644 centreon:centreon rights';
}

// this file should not be erased if it already had a content.
if (file_get_contents($engineContextPath) === '') {
    (new Filesystem())->dumpFile($engineContextPath, $engineContext);
}


$return['result'] = 0;
echo json_encode($return);
exit;