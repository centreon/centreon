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

namespace Tests\www\include\eventLogs\export;

use Centreon\Legacy\EventLogs\Export\Request;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../../../www/include/eventLogs/export/Request.php';

final class RequestTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_GET['id']);
    }

    public function testKeepsAMetaServiceSelectedByAnAdmin(): void
    {
        $request = $this->createRequest('MS_15_42', 1);

        self::assertSame(['_Module_Meta' => [42 => 'meta_42']], $request->getTabSvc());
    }

    public function testCastsTheLabelOfAMetaServiceLikeItsKey(): void
    {
        $request = $this->createRequest('MS_15_042', 1);

        self::assertSame(['_Module_Meta' => [42 => 'meta_42']], $request->getTabSvc());
    }

    /**
     * @dataProvider invalidMetaServiceIdProvider
     */
    public function testKeepsAnEmptyMetaServiceSelectionForAnIdThatIsNotAPositiveInteger(string $openId): void
    {
        $request = $this->createRequest($openId, 1);

        self::assertSame(['_Module_Meta' => []], $request->getTabSvc());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidMetaServiceIdProvider(): iterable
    {
        yield 'non-numeric' => ['MS_15_42abc'];

        yield 'negative' => ['MS_15_-1'];

        yield 'zero' => ['MS_15_0'];

        yield 'decimal' => ['MS_15_4.2'];

        yield 'hexadecimal' => ['MS_15_0x2A'];

        yield 'expression' => ['MS_0) OR 1=1 OR (0'];

        yield 'scalar subquery' => ['MS_0) OR (SELECT 1) OR (0'];

        yield 'several statements' => ['MS_0))); SELECT SLEEP(2) #'];

        yield 'several statements with an update' => ["MS_0))); UPDATE contact SET contact_admin = '1' #"];
    }

    public function testKeepsAMetaServiceAllowedByTheAclOfANonAdminUser(): void
    {
        $request = $this->createRequest('MS_15_42', 0, [15 => [42 => 'meta_42']]);

        self::assertSame(['_Module_Meta' => [42 => 'meta_42']], $request->getTabSvc());
    }

    public function testKeepsAnEmptyMetaServiceSelectionForAMetaServiceNotAllowedByTheAclOfANonAdminUser(): void
    {
        $request = $this->createRequest('MS_15_42', 0, [15 => [43 => 'meta_43']]);

        self::assertSame(['_Module_Meta' => []], $request->getTabSvc());
    }

    public function testKeepsTheValidMetaServicesNextToARejectedOne(): void
    {
        $request = $this->createRequest('MS_15_abc,MS_15_42', 1);

        self::assertSame(['_Module_Meta' => [42 => 'meta_42']], $request->getTabSvc());
    }

    /**
     * @param array<int, array<int, string>> $lcaHost
     */
    private function createRequest(string $openId, int $isAdmin, array $lcaHost = []): Request
    {
        $_GET['id'] = $openId;
        $request = new Request();
        $request->setIsAdmin($isAdmin);
        $request->setLca(['LcaHost' => $lcaHost, 'LcaHostGroup' => [], 'LcaSG' => []]);

        return $request;
    }
}
