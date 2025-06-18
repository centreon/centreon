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

namespace Tests\CentreonRemote\Infrastructure\Export;

use CentreonRemote\Infrastructure\Export\ExportCommitment;
use CentreonRemote\Infrastructure\Export\ExportManifest;
use CentreonRemote\Infrastructure\Export\ExportParserJson;

beforeEach(function () {
    $this->dumpData = [];

    $parser = $this->getMockBuilder(ExportParserJson::class)
        ->onlyMethods(['parse', 'dump'])
        ->getMock();
    $parser->method('parse')
        ->willReturnCallback(function () {
            return [];
        });
    $parser->method('dump')
        ->willReturnCallback(function () {
            $args = func_get_args();
            $this->dumpData[$args[1]] = $args[0];
        });

    $this->commitment = new ExportCommitment(1, [2, 3], null, $parser);
    $this->manifest = $this->getMockBuilder(ExportManifest::class)
        ->onlyMethods(['getFile'])
        ->setConstructorArgs([$this->commitment, '18.10'])
        ->getMock();
    $this->manifest->method('getFile')
        ->willReturn(__FILE__);
});

test('it returns null for missing data', function () {
    expect($this->manifest->get('missing-data'))->toBeNull();
});

test('it dumps the correct data', function () {
    $date = date('l jS \of F Y h:i:s A');
    $this->manifest->dump([
        'date' => $date,
        'remote_server' => $this->commitment->getRemote(),
        'pollers' => $this->commitment->getPollers(),
        'import' => null,
    ]);

    expect($this->dumpData)->toEqual([
        $this->manifest->getFile() => [
            'version' => '18.10',
            'date' => $date,
            'remote_server' => $this->commitment->getRemote(),
            'pollers' => $this->commitment->getPollers(),
            'import' => null,
        ],
    ]);
});
