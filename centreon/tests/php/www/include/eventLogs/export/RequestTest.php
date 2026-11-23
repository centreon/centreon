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

use Centreon\Legacy\EventLogs\Export\Request;

require_once __DIR__ . '/../../../../../../www/include/eventLogs/export/Request.php';

/**
 * @param array<int, array<int, string>> $lcaHost
 */
function createEventLogsExportRequest(string $openId, int $isAdmin, array $lcaHost = []): Request
{
    $_GET['id'] = $openId;
    $request = new Request();
    $request->setIsAdmin($isAdmin);
    $request->setLca(['LcaHost' => $lcaHost, 'LcaHostGroup' => [], 'LcaSG' => []]);

    return $request;
}

afterEach(function (): void {
    unset($_GET['id']);
});

it('keeps a meta-service selected by an admin', function (): void {
    $request = createEventLogsExportRequest('MS_15_42', 1);

    expect($request->getTabSvc())->toBe(['_Module_Meta' => [42 => 'meta_42']]);
});

it('ignores a meta-service id that is not a positive integer', function (string $openId): void {
    $request = createEventLogsExportRequest($openId, 1);

    expect($request->getTabSvc())->toBe([]);
})->with([
    'non-numeric' => ['MS_15_42abc'],
    'negative' => ['MS_15_-1'],
    'zero' => ['MS_15_0'],
    'decimal' => ['MS_15_4.2'],
]);

it('keeps a meta-service allowed by the ACL of a non-admin user', function (): void {
    $request = createEventLogsExportRequest('MS_15_42', 0, [15 => [42 => 'meta_42']]);

    expect($request->getTabSvc())->toBe(['_Module_Meta' => [42 => 'meta_42']]);
});

it('ignores a meta-service not allowed by the ACL of a non-admin user', function (): void {
    $request = createEventLogsExportRequest('MS_15_42', 0, [15 => [43 => 'meta_43']]);

    expect($request->getTabSvc())->toBe([]);
});
