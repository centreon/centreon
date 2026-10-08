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

namespace App\MonitoringConfiguration\Application\Service;

use App\MonitoringConfiguration\Domain\Aggregate\Host\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\Option\OptionName;
use App\MonitoringConfiguration\Domain\Exception\OptionDoesNotExistException;
use App\MonitoringConfiguration\Domain\Repository\OptionRepository;

/**
 * The additive-inheritance flags only mean something when the platform's `inheritance_mode`
 * option enables them; otherwise legacy silently drops whatever the client asked for
 * (`NewHostFactory::create()`), and a fresh install ships that option disabled.
 */
final readonly class AdditiveInheritanceModeApplier
{
    private const INHERITANCE_MODE_OPTION = 'inheritance_mode';
    private const ADDITIVE_INHERITANCE_MODE = 1;

    public function __construct(private OptionRepository $optionRepository)
    {
    }

    public function apply(?Notifications $notifications): ?Notifications
    {
        if (! $notifications instanceof Notifications) {
            return null;
        }

        if (! $notifications->contactAdditiveInheritance && ! $notifications->contactGroupAdditiveInheritance) {
            return $notifications;
        }

        if ($this->isAdditiveInheritanceEnabled()) {
            return $notifications;
        }

        return $notifications->withoutAdditiveInheritance();
    }

    /**
     * `inheritance_mode` is a platform-wide setting of Administration > Parameters; only the
     * value `1` turns additive inheritance on, and a fresh install ships `3`. A missing row reads
     * as disabled, mirroring legacy's own fallback when OptionService returns nothing.
     */
    private function isAdditiveInheritanceEnabled(): bool
    {
        try {
            $option = $this->optionRepository->getByName(new OptionName(self::INHERITANCE_MODE_OPTION));
        } catch (OptionDoesNotExistException) {
            return false;
        }

        // Cast rather than compared as a string: the column is a varchar and legacy reads it
        // with (int) too (AddHost::createHost()), so a stored '01' must not flip the answer.
        return (int) $option->value->value === self::ADDITIVE_INHERITANCE_MODE;
    }
}
