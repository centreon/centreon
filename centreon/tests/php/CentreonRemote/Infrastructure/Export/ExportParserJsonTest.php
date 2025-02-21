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

use CentreonRemote\Infrastructure\Export\ExportParserJson;
use Symfony\Component\Filesystem\Filesystem;

beforeEach(function () {
    $this->fs = new Filesystem();
    $this->fs->mkdir('/tmp');
    $this->parser = new ExportParserJson();
});

afterEach(function () {
    $this->fs->remove('/tmp/test.json');
    $this->fs->remove('/tmp/test2.json');
});

it ('should parse non existent file', function () {
    expect($this->parser->parse('/tmp/test.json'))->toBe([]);
});

it ('should parse file', function () {
    $this->fs->dumpFile('/tmp/test.json', '{"key": "value"}');
    expect($this->parser->parse('/tmp/test.json'))->toBe(['key' => 'value']);
});

it ('should call the callback for a file with macro', function () {
    $this->fs->dumpFile('/tmp/test2.json', '{"key":"@val@"}');

    $result = $this->parser->parse(
        '/tmp/test2.json',
        function (&$result): void {
            $result = str_replace('@val@', 'val', $result);
        }
    );
    expect($result)->toBe(['key' => 'val']);
});

it('should not create manifest file if input is an empty array', function () {
    $this->parser->dump([], '/tmp/test.json');
    expect($this->fs->exists('/tmp/test.json'))->toBeFalse();
});
