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

namespace Tests\www\class;

use Centreon\Test\Mock\CentreonDB;
use CentreonCustomView;
use PHPUnit\Framework\TestCase;

class CentreonCustomViewTest extends TestCase
{
    private const USER_ID = 21;
    private const VIEW_ID = 10;
    private const QUERY_OTHER_USERS_SHARING_VIEW = 'SELECT COUNT(user_id) as "nbuser" '
        . 'FROM custom_view_user_relation '
        . 'WHERE locked = 0 '
        . 'AND is_consumed = 1 '
        . 'AND user_id <> :userId '
        . 'AND custom_view_id = :viewId';

    private CentreonDB $db;

    /** @var list<array<string, mixed>|null> Parameters of each view deletion */
    private array $viewDeletions = [];

    /** @var list<array<string, mixed>|null> Parameters of each user relation deletion */
    private array $userRelationDeletions = [];

    protected function setUp(): void
    {
        $this->db = new CentreonDB();
        $this->db
            ->addResultSet(
                'SELECT contactgroup_cg_id FROM contactgroup_contact_relation WHERE contact_contact_id = :userId',
                []
            )
            ->addResultSet(
                'SELECT custom_view_id FROM custom_view_default WHERE user_id = :userId LIMIT 1',
                []
            )
            ->addResultSet(
                'SELECT cv.custom_view_id, name, layout, is_owner, locked, user_id, usergroup_id, public '
                . 'FROM custom_views cv, custom_view_user_relation cvur '
                . 'WHERE cv.custom_view_id = cvur.custom_view_id '
                . 'AND (cvur.user_id = ? ) AND is_consumed = 1 ORDER BY user_id, name',
                [
                    [
                        'custom_view_id' => self::VIEW_ID,
                        'name' => 'Shared view',
                        'layout' => 'column_1',
                        'is_owner' => 0,
                        'locked' => 0,
                        'user_id' => self::USER_ID,
                        'usergroup_id' => null,
                        'public' => 0,
                    ],
                ]
            )
            // The owner of the view has left it
            ->addResultSet(
                'SELECT is_consumed FROM custom_view_user_relation WHERE is_owner = 1 AND custom_view_id = :viewId',
                [['is_consumed' => 0]],
                [':viewId' => self::VIEW_ID]
            )
            ->addResultSet(
                'DELETE FROM custom_views WHERE custom_view_id = :viewId',
                [],
                null,
                function (?array $params): void {
                    $this->viewDeletions[] = $params;
                }
            )
            ->addResultSet(
                'DELETE FROM custom_view_user_relation WHERE user_id = :userId AND custom_view_id = :viewId',
                [],
                null,
                function (?array $params): void {
                    $this->userRelationDeletions[] = $params;
                }
            );
    }

    public function testNonOwnerRemovingViewLeftByItsOwnerOnlyDropsOwnRelation(): void
    {
        $this->db
            ->addResultSet(
                self::QUERY_OTHER_USERS_SHARING_VIEW,
                [['nbuser' => 1]],
                [':userId' => self::USER_ID, ':viewId' => self::VIEW_ID]
            )
            ->addResultSet(self::QUERY_OTHER_USERS_SHARING_VIEW, [['nbuser' => 0]]);

        (new CentreonCustomView(null, $this->db, self::USER_ID))->deleteCustomView(self::VIEW_ID, true);

        $this->assertSame([], $this->viewDeletions);
        $this->assertSame(
            [[':userId' => self::USER_ID, ':viewId' => self::VIEW_ID]],
            $this->userRelationDeletions
        );
    }

    public function testLastNonOwnerRemovingViewLeftByItsOwnerDeletesIt(): void
    {
        $this->db
            ->addResultSet(
                self::QUERY_OTHER_USERS_SHARING_VIEW,
                [['nbuser' => 0]],
                [':userId' => self::USER_ID, ':viewId' => self::VIEW_ID]
            )
            ->addResultSet(self::QUERY_OTHER_USERS_SHARING_VIEW, [['nbuser' => 1]]);

        (new CentreonCustomView(null, $this->db, self::USER_ID))->deleteCustomView(self::VIEW_ID, true);

        $this->assertSame([[':viewId' => self::VIEW_ID]], $this->viewDeletions);
        $this->assertSame([], $this->userRelationDeletions);
    }
}
