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

namespace Tests\App\MonitoringConfiguration\Domain\Aggregate\HostTemplate;

use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateCheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateMacro;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateNotificationsChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateRelationsChange;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Exception\DuplicateHostTemplateMacroNameException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateExclusiveNotificationOptionException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateLockedException;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;

final class HostTemplateRelationsTest extends TestCase
{
    public function testTheRelationsGivenReplaceTheCurrentOnesAndTheOthersStay(): void
    {
        $template = $this->template();

        $changed = $template->withRelations(new HostTemplateRelationsChange(
            parentTemplateIds: new Collection([new HostTemplateId(3)], HostTemplateId::class),
            contactIds: new Collection([new NotificationContactId(31)], NotificationContactId::class),
        ));

        self::assertSame([3], $this->values($changed->parentTemplateIds));
        self::assertSame([10], $this->values($changed->categoryIds));
        self::assertSame([31], $this->values($changed->notifications->contactIds ?? new Collection([], NotificationContactId::class)));
        self::assertSame([40], $this->values($changed->notifications->contactGroupIds ?? new Collection([], ContactGroupId::class)));
        self::assertSame(30, $changed->notifications?->interval);
    }

    public function testContactsGivenToATemplateWithoutNotificationsGetTheDefaults(): void
    {
        $template = new HostTemplate(new HostTemplateId(7), new HostTemplateName('tpl'));

        $changed = $template->withRelations(new HostTemplateRelationsChange(contactIds: new Collection([new NotificationContactId(31)], NotificationContactId::class)));

        self::assertSame([31], $this->values($changed->notifications->contactIds ?? new Collection([], NotificationContactId::class)));
        self::assertTrue($changed->hasSameLoggedConfigurationAs($template));
        self::assertFalse($changed->hasSameContactsAs($template));
    }

    public function testALockedTemplateRefusesRelationsAndMacros(): void
    {
        $template = new HostTemplate(new HostTemplateId(7), new HostTemplateName('tpl'), isLocked: true);

        try {
            $template->withRelations(new HostTemplateRelationsChange());
            self::fail('A locked template must refuse its relations.');
        } catch (HostTemplateLockedException) {
        }

        $this->expectException(HostTemplateLockedException::class);

        $template->withMacros(new Collection([], HostTemplateMacro::class));
    }

    public function testTwoMacrosOfOneNameAreRefused(): void
    {
        $this->expectException(DuplicateHostTemplateMacroNameException::class);

        $this->template()->withMacros(new Collection([$this->macro('PORT', '1'), $this->macro('PORT', '2')], HostTemplateMacro::class));
    }

    public function testTheParentsAndTheLinkedServiceTemplatesCompareInOrderTheCategoriesAsASet(): void
    {
        $template = $this->template();

        $sameCategoriesReordered = $template->withRelations(new HostTemplateRelationsChange(categoryIds: new Collection([new HostCategoryId(10)], HostCategoryId::class)));
        $parentsReordered = $template->withRelations(new HostTemplateRelationsChange(parentTemplateIds: new Collection([new HostTemplateId(2), new HostTemplateId(1)], HostTemplateId::class)));
        $linkedReordered = $template->withRelations(new HostTemplateRelationsChange(linkedServiceTemplateIds: new Collection([new ServiceTemplateId(21), new ServiceTemplateId(20)], ServiceTemplateId::class)));
        $categoriesChanged = $template->withRelations(new HostTemplateRelationsChange(categoryIds: new Collection([new HostCategoryId(11)], HostCategoryId::class)));

        self::assertTrue($sameCategoriesReordered->hasSameRelationsAs($template));
        self::assertFalse($parentsReordered->hasSameRelationsAs($template));
        self::assertFalse($linkedReordered->hasSameRelationsAs($template));
        self::assertFalse($categoriesChanged->hasSameRelationsAs($template));
    }

    public function testTheContactsCountAsConfigurationButAreNotLogged(): void
    {
        $template = $this->template();

        $changed = $template->withRelations(new HostTemplateRelationsChange(contactGroupIds: new Collection([], ContactGroupId::class)));

        self::assertFalse($changed->hasSameConfigurationAs($template));
        self::assertTrue($changed->hasSameLoggedConfigurationAs($template));
        self::assertTrue($changed->hasSameRelationsAs($template));
    }

    public function testTheMacrosCompareAsASetByName(): void
    {
        $template = $this->template();

        self::assertTrue($template->withMacros(new Collection([$this->macro('B', '2'), $this->macro('A', '1')], HostTemplateMacro::class))->hasSameMacrosAs($template));
        self::assertFalse($template->withMacros(new Collection([$this->macro('A', '1'), $this->macro('B', '3')], HostTemplateMacro::class))->hasSameMacrosAs($template));
        self::assertFalse($template->withMacros(new Collection([$this->macro('A', '1'), $this->macro('B', '2', true)], HostTemplateMacro::class))->hasSameMacrosAs($template));
        self::assertFalse($template->withMacros(new Collection([$this->macro('A', '1')], HostTemplateMacro::class))->hasSameMacrosAs($template));
        self::assertTrue($template->withMacros(new Collection([$this->macro('A', '1'), $this->macro('B', '2')], HostTemplateMacro::class))->hasSameLoggedConfigurationAs($template));
    }

    public function testTheOptionsCompareAsASetAndAreLogged(): void
    {
        $template = $this->template();

        $reordered = $template->withChange(new HostTemplateChange(notifications: new HostTemplateNotificationsChange(
            options: [NotificationOptionEnum::Recovery, NotificationOptionEnum::Down],
        )));
        $changed = $template->withChange(new HostTemplateChange(notifications: new HostTemplateNotificationsChange(
            options: [NotificationOptionEnum::Down],
        )));

        self::assertTrue($reordered->hasSameConfigurationAs($template));
        self::assertFalse($changed->hasSameLoggedConfigurationAs($template));
    }

    public function testNoneCannotEndUpWithAnotherOption(): void
    {
        $this->expectException(HostTemplateExclusiveNotificationOptionException::class);

        $this->template()->withChange(new HostTemplateChange(notifications: new HostTemplateNotificationsChange(
            options: [NotificationOptionEnum::Down, NotificationOptionEnum::None],
        )));
    }

    public function testNoneAloneIsAccepted(): void
    {
        $changed = $this->template()->withChange(new HostTemplateChange(notifications: new HostTemplateNotificationsChange(
            options: [NotificationOptionEnum::None],
        )));

        self::assertSame([NotificationOptionEnum::None], $changed->notifications?->options);
    }

    private function template(): HostTemplate
    {
        return new HostTemplate(
            new HostTemplateId(7),
            new HostTemplateName('tpl'),
            new HostTemplateCheckOptions(macros: new Collection([$this->macro('A', '1'), $this->macro('B', '2')], HostTemplateMacro::class)),
            parentTemplateIds: new Collection([new HostTemplateId(1), new HostTemplateId(2)], HostTemplateId::class),
            notifications: new HostTemplateNotifications(
                contactIds: new Collection([new NotificationContactId(30)], NotificationContactId::class),
                contactGroupIds: new Collection([new ContactGroupId(40)], ContactGroupId::class),
                options: [NotificationOptionEnum::Down, NotificationOptionEnum::Recovery],
                interval: 30,
            ),
            categoryIds: new Collection([new HostCategoryId(10)], HostCategoryId::class),
            linkedServiceTemplateIds: new Collection([new ServiceTemplateId(20), new ServiceTemplateId(21)], ServiceTemplateId::class),
        );
    }

    private function macro(string $name, string $value, bool $isPassword = false): HostTemplateMacro
    {
        return new HostTemplateMacro(new HostMacroName($name), $value, $isPassword, new HostMacroId(ord($name)));
    }

    /**
     * @template T of \App\Shared\Domain\Aggregate\AggregateRootId
     *
     * @param Collection<T> $ids
     *
     * @return list<int>
     */
    private function values(Collection $ids): array
    {
        return array_values(array_map(static fn (\App\Shared\Domain\Aggregate\AggregateRootId $id): int => $id->value, $ids->toArray()));
    }
}
