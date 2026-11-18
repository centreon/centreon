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

namespace Tests\App\MonitoringConfiguration\Domain\Model;

use App\MonitoringConfiguration\Domain\Model\UrlPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UrlPathTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function validPathProvider(): iterable
    {
        yield 'root-mounted platform' => [''];

        yield 'single segment' => ['/centreon'];

        yield 'multi-segment' => ['/base/path'];

        yield 'dots inside a segment' => ['/index.php'];

        yield 'underscore and hyphen' => ['/my_base-path'];

        yield 'digits' => ['/v2'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPathProvider(): iterable
    {
        // What CentralAddress relies on to keep rejecting "host//path".
        yield 'empty segment' => ['//path'];

        yield 'single-dot segment' => ['/.'];

        yield 'double-dot segment' => ['/..'];

        yield 'dot segment inside the path' => ['/platform/../admin'];

        yield 'leading dot segment' => ['/./platform'];

        // The value is interpolated unquoted into `curl … | bash`, so shell metacharacters are
        // rejected rather than escaped.
        yield 'command separator' => ['/centreon;id'];

        yield 'pipe' => ['/centreon|id'];

        yield 'command substitution' => ['/$(id)'];

        yield 'backtick substitution' => ['/`id`'];

        yield 'inner whitespace' => ['/base path'];

        yield 'query string' => ['/centreon?foo=bar'];

        yield 'fragment' => ['/centreon#anchor'];

        yield 'embedded newline' => ["/centreon\nid"];

        // "$" on its own would match right before this one, and CentralUrlFactory::baseUri() rtrims
        // "/" alone, so the newline reaches the constructor intact.
        yield 'trailing newline' => ["/centreon\n"];
    }

    /**
     * Nothing is repaired on the way in, so a caller cannot get a malformed path quietly turned
     * into a valid one. Both forms are the callers' job to normalize before construction.
     *
     * @return iterable<string, array{string}>
     */
    public static function unnormalizedPathProvider(): iterable
    {
        yield 'trailing slash' => ['/centreon/'];

        yield 'no leading slash' => ['centreon'];
    }

    #[DataProvider('validPathProvider')]
    public function testAcceptsValidPathVerbatim(string $rawValue): void
    {
        self::assertSame($rawValue, new UrlPath($rawValue)->value);
    }

    #[DataProvider('invalidPathProvider')]
    public function testRejectsInvalidPath(string $rawValue): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new UrlPath($rawValue);
    }

    #[DataProvider('unnormalizedPathProvider')]
    public function testRejectsRatherThanNormalizes(string $rawValue): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new UrlPath($rawValue);
    }

    /**
     * CentralAddress passes its own property path so a base path failure keeps pointing at the
     * address the admin typed, not at this type.
     */
    public function testReportsTheGivenPropertyPath(): void
    {
        $this->expectExceptionMessage('[CentralAddress::value] The path "/.." must not contain dot segments');

        new UrlPath('/..', 'CentralAddress::value');
    }

    public function testExposesSegmentsWithoutTheLeadingSlash(): void
    {
        self::assertSame('base/path', new UrlPath('/base/path')->segments());
    }

    public function testExposesNoSegmentForAnEmptyPath(): void
    {
        self::assertNull(new UrlPath('')->segments());
    }

    public function testTryFromYieldsNullInsteadOfThrowing(): void
    {
        self::assertNull(UrlPath::tryFrom('/platform/../admin'));
    }

    public function testTryFromBuildsTheValueObjectWhenThePathIsValid(): void
    {
        self::assertSame('/centreon', UrlPath::tryFrom('/centreon')?->value);
    }
}
