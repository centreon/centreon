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

use App\MonitoringConfiguration\Application\Command\ListChange;
use App\MonitoringConfiguration\Application\Command\PatchServiceCommand;
use App\MonitoringConfiguration\Application\Command\ServiceDataProcessingChanges;
use App\MonitoringConfiguration\Application\Command\ServiceExtendedInformationsChanges;
use App\MonitoringConfiguration\Application\Command\ServiceSchedulingOptionsChanges;
use App\MonitoringConfiguration\Domain\Aggregate\GraphTemplate\GraphTemplateId;
use App\MonitoringConfiguration\Domain\Exception\ServiceFieldsNotAvailableOnCloudException;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\NoValue;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The fields of a service a Cloud platform does not have, refused by a partial update there. Clearing one, back to
 * its default, is accepted. The service template cannot be removed.
 */
final readonly class ServicePlatformGuard
{
    public function __construct(
        #[Autowire(env: 'bool:default::IS_CLOUD_PLATFORM')]
        private bool $isCloudPlatform = false,
    ) {
    }

    /**
     * @throws ServiceFieldsNotAvailableOnCloudException
     */
    public function assertAvailable(PatchServiceCommand $command): void
    {
        if (! $this->isCloudPlatform) {
            return;
        }

        $criteria = array_filter([
            'serviceTemplateId' => $command->templateId === null,
            'graphTemplateId' => $command->graphTemplateId instanceof GraphTemplateId,
            'trapIds' => $this->bringsValues($command->trapIds),
            'notifications' => ! $command->notifications instanceof NoValue
                || ! $command->contactIds instanceof NoValue
                || ! $command->contactGroupIds instanceof NoValue,
            'monitoringEngine' => $this->monitoringEngineFields($command->schedulingOptions, $command->extendedInformations),
            'advanced' => $this->advancedFields($command->dataProcessing, $command->extendedInformations),
        ]);

        if ($criteria !== []) {
            throw new ServiceFieldsNotAvailableOnCloudException($criteria);
        }
    }

    /**
     * @return list<string>
     */
    private function monitoringEngineFields(NoValue|ServiceSchedulingOptionsChanges $scheduling, NoValue|ServiceExtendedInformationsChanges $informations): array
    {
        $fields = [];
        if ($scheduling instanceof ServiceSchedulingOptionsChanges) {
            $fields = array_keys(array_filter(
                [
                    'activeCheckEnabled' => $scheduling->activeCheckEnabled,
                    'passiveCheckEnabled' => $scheduling->passiveCheckEnabled,
                    'isVolatile' => $scheduling->isVolatile,
                ],
                $this->setsADirective(...),
            ));
        }

        if ($informations instanceof ServiceExtendedInformationsChanges && is_string($informations->altIcon)) {
            $fields[] = 'altIcon';
        }

        return $fields;
    }

    /**
     * @return list<string>
     */
    private function advancedFields(NoValue|ServiceDataProcessingChanges $dataProcessing, NoValue|ServiceExtendedInformationsChanges $informations): array
    {
        $fields = [];
        if ($dataProcessing instanceof ServiceDataProcessingChanges) {
            $fields = array_keys(array_filter(
                [
                    'obsessOverService' => $this->setsADirective($dataProcessing->obsessOverService),
                    'flapDetectionEnabled' => $this->setsADirective($dataProcessing->flapDetectionEnabled),
                    'retainStatusInformation' => $this->setsADirective($dataProcessing->retainStatusInformation),
                    'retainNonStatusInformation' => $this->setsADirective($dataProcessing->retainNonStatusInformation),
                    'acknowledgmentTimeout' => is_int($dataProcessing->acknowledgmentTimeout),
                    'lowFlapThreshold' => is_int($dataProcessing->lowFlapThreshold),
                    'highFlapThreshold' => is_int($dataProcessing->highFlapThreshold),
                    'stalkingOptions' => $this->bringsValues($dataProcessing->stalkingOptions),
                    'eventHandlerArgs' => is_array($dataProcessing->eventHandlerArgs) && $dataProcessing->eventHandlerArgs !== [],
                ],
            ));
        }

        if ($informations instanceof ServiceExtendedInformationsChanges && is_string($informations->comment)) {
            $fields[] = 'comment';
        }

        return $fields;
    }

    private function setsADirective(NoValue|TriStateEnum $value): bool
    {
        return $value instanceof TriStateEnum && $value !== TriStateEnum::UseDefault;
    }

    /**
     * @template T
     *
     * @param NoValue|ListChange<T> $change
     */
    private function bringsValues(NoValue|ListChange $change): bool
    {
        return $change instanceof ListChange && $change->values !== [];
    }
}
