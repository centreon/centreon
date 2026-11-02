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

namespace App\ActivityLogging\Domain\Service;

use App\ActivityLogging\Domain\ReadModel\ActivityLogDetails;
use App\ActivityLogging\Domain\ReadModel\FieldChange;

/**
 * Computes the before/after values of the fields changed by one activity log.
 *
 * Logs only store the values submitted at each change, so the "before" value of a field is found by
 * replaying the target's history up to the requested log. Port of the legacy
 * CentreonLogAction::listModification(), password masking included: passwords never leave the server.
 */
final readonly class ChangeHistoryBuilder
{
    public const PASSWORD_BEFORE = '*******';
    public const PASSWORD_AFTER = '******';
    private const MACRO_PASSWORD_REFERENCE_FIELD = 'refMacroPassword';
    private const MACRO_VALUE_FIELD = 'macroValue';
    private const MACRO_OLD_VALUE_FIELD_PREFIX = 'macroOldValue_';
    private const CONTACT_PASSWORD_FIELDS = ['contact_passwd', 'contact_passwd2'];

    /**
     * @param iterable<ActivityLogDetails> $history the target's logs, oldest first
     *
     * @return list<FieldChange> the changes of the $activityLogId log, empty when it is not part of $history
     */
    public function build(int $activityLogId, iterable $history): array
    {
        /** @var array<string, string> $previousValues */
        $previousValues = [];

        foreach ($history as $details) {
            $changes = $this->replay($details, $previousValues);

            if ($details->activityLogId === $activityLogId) {
                return $changes;
            }
        }

        return [];
    }

    /**
     * @param array<string, string> $previousValues the last value of each field, updated with the replayed log
     *
     * @return list<FieldChange>
     */
    private function replay(ActivityLogDetails $details, array &$previousValues): array
    {
        $macroPasswordIndexes = $this->findMacroPasswordIndexes($details);
        $changes = [];

        foreach ($details->fields as ['name' => $name, 'value' => $value]) {
            if ($name === self::MACRO_VALUE_FIELD) {
                // the macro values are stored as a comma separated list, mask the password ones
                $macroValues = explode(',', $value);
                foreach ($macroPasswordIndexes as $index) {
                    if (! empty($macroValues[$index])) {
                        $macroValues[$index] = self::PASSWORD_AFTER;
                    }
                }
                $value = implode(',', $macroValues);

                if (isset($previousValues[$name]) && str_replace(',', '', $previousValues[$name]) !== '') {
                    // the legacy rebuilt the "before" value from the current macro values, showing the new
                    // values on both sides: mask the previous values instead
                    $previousMacroValues = explode(',', $previousValues[$name]);
                    foreach ($macroPasswordIndexes as $index) {
                        $previousMacroValues[$index] = self::PASSWORD_BEFORE;
                    }
                    $previousValues[$name] = implode(',', $previousMacroValues);
                }
            } elseif (in_array($name, self::CONTACT_PASSWORD_FIELDS, true)) {
                $value = self::PASSWORD_AFTER;
                if (isset($previousValues[$name])) {
                    $previousValues[$name] = self::PASSWORD_BEFORE;
                }
            }

            $isMacroPasswordOldValue = $this->isMacroPasswordOldValue($name, $macroPasswordIndexes);

            if (! isset($previousValues[$name]) && $value !== '') {
                $changes[] = new FieldChange(
                    field: $name,
                    before: null,
                    after: $isMacroPasswordOldValue ? self::PASSWORD_AFTER : $value,
                );
            } elseif (isset($previousValues[$name]) && $previousValues[$name] !== $value) {
                $changes[] = new FieldChange(
                    field: $name,
                    before: $isMacroPasswordOldValue ? self::PASSWORD_BEFORE : $this->nullIfEmpty($previousValues[$name]),
                    after: $isMacroPasswordOldValue ? self::PASSWORD_AFTER : $this->nullIfEmpty($value),
                );
            }

            $previousValues[$name] = $value;
        }

        return $changes;
    }

    /**
     * The legacy writer stores the indexes of the password macros in a dedicated field.
     *
     * @return list<string>
     */
    private function findMacroPasswordIndexes(ActivityLogDetails $details): array
    {
        foreach ($details->fields as ['name' => $name, 'value' => $value]) {
            if ($name === self::MACRO_PASSWORD_REFERENCE_FIELD) {
                return explode(',', $value);
            }
        }

        return [];
    }

    /**
     * @param list<string> $macroPasswordIndexes
     */
    private function isMacroPasswordOldValue(string $fieldName, array $macroPasswordIndexes): bool
    {
        foreach ($macroPasswordIndexes as $index) {
            if (str_contains($fieldName, self::MACRO_OLD_VALUE_FIELD_PREFIX . $index)) {
                return true;
            }
        }

        return false;
    }

    private function nullIfEmpty(string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
