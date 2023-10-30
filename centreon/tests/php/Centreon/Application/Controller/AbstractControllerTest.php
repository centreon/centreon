<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Tests\Centreon\Application\Controller;

use Centreon\Application\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

beforeEach(function (): void {
    $this->validateDataSentController = new class extends AbstractController {
        // We expose a public method to make the protected function testable.
        public function testValidateDataSent(Request $request, string $jsonSchema): ?string
        {
            // Write the json schema in a temporary file
            $jsonTempFile = tempnam(sys_get_temp_dir(), 'jsonSchema');
            if (false === $jsonTempFile || false === file_put_contents($jsonTempFile, $jsonSchema)) {
                throw new \Exception('Failed to create a temporary JSON schema file for the AbstractControllerTest');
            }

            try {
                // Call of the protected method.
                $this->validateDataSent($request, $jsonTempFile);

                return null;
            } catch (\InvalidArgumentException $ex) {
                return $ex->getMessage();
            }
        }
    };
});

// ────────────────────────────── FAILURES tests ──────────────────────────────

it(
    'should NOT validate',
    function (string $error, string $content, string $jsonSchema): void {
        $request = $this->createMock(Request::class);
        $request->method('getContent')->willReturn($content);
        $return = $this->validateDataSentController->testValidateDataSent($request, $jsonSchema);
        if ($return === null) {
            $this->fail('Validated despite all expectations !!');
        }
        expect($return)->toContain($error);
    }
)->with([
    'JSON is an integer' => ['Error when decoding your sent data', '42', ''],
    'JSON is a float' => ['Error when decoding your sent data', '1.23', ''],
    'JSON is a string' => ['Error when decoding your sent data', '"foo"', ''],
    'JSON is a boolean' => ['Error when decoding your sent data', 'false', ''],
    'JSON is a null' => ['Error when decoding your sent data', 'null', ''],
    'JSON is an emtpy array, expect an object' => [
        'Array value found, but an object is required',
        '[]',
        <<<'JSON'
            {
                "$schema": "http://json-schema.org/draft-04/schema#",
                "type": "object"
            }
            JSON
    ],
    'JSON is an emtpy object, expect an array' => [
        'Object value found, but an array is required',
        '{}',
        <<<'JSON'
            {
                "$schema": "http://json-schema.org/draft-04/schema#",
                "type": "array",
                "items": { "type": "object" }
            }
            JSON
    ],
    'JSON is an object containing an sub-object, expect an sub-array' => [
        '[myProperty] Object value found, but an array is required',
        '{"myProperty": {} }',
        <<<'JSON'
            {
                "$schema": "http://json-schema.org/draft-04/schema#",
                "type": "object",
                "properties": {
                    "myProperty": { "type": "array" }
                }
            }
            JSON
    ],
    'JSON is an object containing an sub-array, expect an sub-object' => [
        '[myProperty] Array value found, but an object is required',
        '{"myProperty": [] }',
        <<<'JSON'
            {
                "$schema": "http://json-schema.org/draft-04/schema#",
                "type": "object",
                "properties": {
                    "myProperty": { "type": "object" }
                }
            }
            JSON
    ],
]);

// ────────────────────────────── SUCCESS tests ──────────────────────────────

it(
    'should validate',
    function (string $content, string $jsonSchema): void {
        $request = $this->createMock(Request::class);
        $request->method('getContent')->willReturn($content);
        $return = $this->validateDataSentController->testValidateDataSent($request, $jsonSchema);
        expect($return)->toBeNull();
    }
)->with([
    'JSON is an array of objects' => [
        '[{}]',
        <<<'JSON'
            {
                "$schema": "http://json-schema.org/draft-04/schema#",
                "type": "array",
                "items": { "type": "object" }
            }
            JSON
    ],
    'JSON is an array of objects but empty' => [
        '[]',
        <<<'JSON'
            {
                "$schema": "http://json-schema.org/draft-04/schema#",
                "type": "array",
                "items": { "type": "object" }
            }
            JSON
    ],
    'JSON is an empty object' => [
        '{}',
        <<<'JSON'
            {
                "$schema": "http://json-schema.org/draft-04/schema#",
                "type": "object"
            }
            JSON
    ],
    'JSON is an object containing an sub-object' => [
        '{"myProperty": [] }',
        <<<'JSON'
            {
                "$schema": "http://json-schema.org/draft-04/schema#",
                "type": "object",
                "properties": {
                    "myProperty": { "type": "array" }
                }
            }
            JSON
    ],
    'JSON is an object containing an sub-array' => [
        '{"myProperty": {} }',
        <<<'JSON'
            {
                "$schema": "http://json-schema.org/draft-04/schema#",
                "type": "object",
                "properties": {
                    "myProperty": { "type": "object" }
                }
            }
            JSON
    ],
]);
