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

namespace Tests\Core\Dashboard\Playlist\Domain\Model;

use Centreon\Domain\Common\Assertion\AssertionException;
use Core\Dashboard\Playlist\Domain\Exception\NewPlaylistException;
use Core\Dashboard\Playlist\Domain\Model\DashboardOrder;
use Core\Dashboard\Playlist\Domain\Model\NewPlaylist;

it('should throw an exception when the playlist name is empty', function() {
    $name = str_repeat('a', NewPlaylist::NAME_MIN_LENGTH - 1);
    new NewPlaylist($name, 10, false);
})->throws(AssertionException::minLength(
    str_repeat('a', NewPlaylist::NAME_MIN_LENGTH - 1),
    NewPlaylist::NAME_MIN_LENGTH - 1,
    NewPlaylist::NAME_MIN_LENGTH,
    'NewPlaylist::name'
)->getMessage());

it('should throw an exception when the playlist name is too long', function() {
    $name = str_repeat('a', NewPlaylist::NAME_MAX_LENGTH + 1);
    new NewPlaylist($name, 10, false);
})->throws(AssertionException::maxLength(
    str_repeat('a', NewPlaylist::NAME_MAX_LENGTH + 1),
    NewPlaylist::NAME_MAX_LENGTH + 1,
    NewPlaylist::NAME_MAX_LENGTH,
    'NewPlaylist::name'
)->getMessage());

it('should throw an exception when rotation time is out of range', function() {
    new NewPlaylist('playlist', NewPlaylist::MINIMUM_ROTATION_TIME - 1, false);
})->throws(AssertionException::range(
    NewPlaylist::MINIMUM_ROTATION_TIME - 1,
    NewPlaylist::MINIMUM_ROTATION_TIME,
    NewPlaylist::MAXIMUM_ROTATION_TIME,
    'NewPlaylist::name'
)->getMessage());

it('should throw an exception when the description is too short', function () {
    (new NewPlaylist('playlist', 10, false))->setDescription(str_repeat('a', NewPlaylist::DESCRIPTION_MIN_LENGTH - 1));
})->throws(AssertionException::minLength(
    str_repeat('a', NewPlaylist::DESCRIPTION_MIN_LENGTH - 1),
    NewPlaylist::DESCRIPTION_MIN_LENGTH - 1,
    NewPlaylist::DESCRIPTION_MIN_LENGTH,
    'NewPlaylist::description'
)->getMessage());

it('should throw an exception when the description is too long', function () {
    (new NewPlaylist('playlist', 10, false))->setDescription(str_repeat('a', NewPlaylist::DESCRIPTION_MAX_LENGTH + 1));
})->throws(AssertionException::maxLength(
    str_repeat('a', NewPlaylist::DESCRIPTION_MAX_LENGTH + 1),
    NewPlaylist::DESCRIPTION_MAX_LENGTH + 1,
    NewPlaylist::DESCRIPTION_MAX_LENGTH,
    'NewPlaylist::description'
)->getMessage());

it('should throw an exception when many dashboards has the same order', function (){
    (new NewPlaylist('playlist', 10, false))
        ->setDashboardsOrder([new DashboardOrder(1, 1), new DashboardOrder(2, 1)]);
})->throws(NewPlaylistException::orderMustBeUnique()->getMessage());