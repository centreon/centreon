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

namespace Tests\Centreon\Infrastructure\RequestParameters;

use Centreon\Infrastructure\RequestParameters\RegularExpressionSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RegularExpressionSanitizerTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function provideRegularExpressions(): array
    {
        return [
            'literal braces of a resource name' => ['FR-WIZ_-_Wizernes_{Deroo}', 'FR-WIZ_-_Wizernes_\{Deroo\}'],
            'lone opening brace' => ['host{01', 'host\{01'],
            'lone closing brace' => ['host}01', 'host\}01'],
            'empty braces' => ['host{}01', 'host\{\}01'],
            'interval without a minimum' => ['host{,3}', 'host\{,3\}'],
            'braces inside a character class' => ['[{}]', '[\{\}]'],
            'exact interval' => ['ho{2}st', 'ho{2}st'],
            'bounded interval' => ['ho{2,3}st', 'ho{2,3}st'],
            'unbounded interval' => ['ho{2,}st', 'ho{2,}st'],
            'interval followed by literal braces' => ['ho{2,3}st{Deroo}', 'ho{2,3}st\{Deroo\}'],
            'already escaped braces' => ['host\{01\}', 'host\{01\}'],
            'escaped brace followed by an interval' => ['host\{{2}', 'host\{{2}'],
            'no brace at all' => ['^host.*$', '^host.*$'],
            'multibyte characters' => ['Wizernes_éàü_{Deroo}', 'Wizernes_éàü_\{Deroo\}'],
            'empty value' => ['', ''],
        ];
    }

    /**
     * The sanitized pattern must be exactly the expected one, and must still compile.
     *
     * @param string $regularExpression
     * @param string $expected
     */
    #[DataProvider('provideRegularExpressions')]
    public function testSanitize(string $regularExpression, string $expected): void
    {
        $sanitized = RegularExpressionSanitizer::sanitize($regularExpression);

        $this->assertSame($expected, $sanitized);
        $this->assertNotFalse(@preg_match('/' . $sanitized . '/', ''));
        $this->assertSame(PREG_NO_ERROR, preg_last_error());
    }
}
