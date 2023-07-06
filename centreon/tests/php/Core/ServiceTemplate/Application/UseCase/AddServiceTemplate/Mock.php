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

namespace Tests\Core\ServiceTemplate\Application\UseCase\AddServiceTemplate;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Option\OptionService;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\CommandMacro\Application\Repository\ReadCommandMacroRepositoryInterface;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Macro\Application\Repository\ReadServiceMacroRepositoryInterface;
use Core\Macro\Application\Repository\WriteServiceMacroRepositoryInterface;
use Core\PerformanceGraph\Application\Repository\ReadPerformanceGraphRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Application\Repository\WriteServiceCategoryRepositoryInterface;
use Core\ServiceSeverity\Application\Repository\ReadServiceSeverityRepositoryInterface;
use Core\ServiceTemplate\Application\Repository\ReadServiceTemplateRepositoryInterface;
use Core\ServiceTemplate\Application\Repository\WriteServiceTemplateRepositoryInterface;
use Core\ServiceTemplate\Application\UseCase\AddServiceTemplate\AddServiceTemplate;
use Core\TimePeriod\Application\Repository\ReadTimePeriodRepositoryInterface;
use Core\ViewImg\Application\Repository\ReadViewImgRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Tests\Core\ServiceTemplate\Infrastructure\API\AddServiceTemplate\AddServiceTemplatePresenterStub;

class Mock extends TestCase
{
    public static function create(TestCase $testCase): void
    {
        $testCase->readServiceTemplateRepository = $testCase->createMock(ReadServiceTemplateRepositoryInterface::class);
        $testCase->writeServiceTemplateRepository = $testCase->createMock(
            WriteServiceTemplateRepositoryInterface::class
        );
        $testCase->serviceSeverityRepository = $testCase->createMock(ReadServiceSeverityRepositoryInterface::class);
        $testCase->performanceGraphRepository = $testCase->createMock(ReadPerformanceGraphRepositoryInterface::class);
        $testCase->commandRepository = $testCase->createMock(ReadCommandRepositoryInterface::class);
        $testCase->timePeriodRepository = $testCase->createMock(ReadTimePeriodRepositoryInterface::class);
        $testCase->imageRepository = $testCase->createMock(ReadViewImgRepositoryInterface::class);
        $testCase->readHostTemplateRepository = $testCase->createMock(ReadHostTemplateRepositoryInterface::class);
        $testCase->readServiceMacroRepository = $testCase->createMock(ReadServiceMacroRepositoryInterface::class);
        $testCase->readCommandMacroRepository = $testCase->createMock(ReadCommandMacroRepositoryInterface::class);
        $testCase->writeServiceMacroRepository = $testCase->createMock(WriteServiceMacroRepositoryInterface::class);
        $testCase->storageEngine = $testCase->createMock(DataStorageEngineInterface::class);
        $testCase->readServiceCategoryRepository = $testCase->createMock(ReadServiceCategoryRepositoryInterface::class);
        $testCase->writeServiceCategoryRepository = $testCase->createMock(WriteServiceCategoryRepositoryInterface::class);
        $testCase->readAccessGroupRepository = $testCase->createMock(ReadAccessGroupRepositoryInterface::class);
        $testCase->optionService = $testCase->createMock(OptionService::class);
        $testCase->user = $testCase->createMock(ContactInterface::class);
        $testCase->useCasePresenter = new AddServiceTemplatePresenterStub(
            $testCase->createMock(PresenterFormatterInterface::class)
        );
        $testCase->addUseCase = new AddServiceTemplate(
            $testCase->readServiceTemplateRepository,
            $testCase->writeServiceTemplateRepository,
            $testCase->serviceSeverityRepository,
            $testCase->performanceGraphRepository,
            $testCase->commandRepository,
            $testCase->timePeriodRepository,
            $testCase->imageRepository,
            $testCase->readHostTemplateRepository,
            $testCase->readServiceMacroRepository,
            $testCase->readCommandMacroRepository,
            $testCase->writeServiceMacroRepository,
            $testCase->storageEngine,
            $testCase->readServiceCategoryRepository,
            $testCase->writeServiceCategoryRepository,
            $testCase->readAccessGroupRepository,
            $testCase->optionService,
            $testCase->user
        );
    }

    /**
     * @param TestCase $testCase
     * @param array<string, array<array{method: string, arguments: mixed, expected: mixed}>> $mockOptions
     */
    public static function setMock(TestCase $testCase, array $mockOptions): void
    {
        foreach ($mockOptions as $mockName => $options) {
            foreach ($options as $option) {
                switch ($mockName) {
                    case 'user':
                        $testCase->user
                            ->expects($testCase->once())
                            ->method('hasTopologyRole')
                            ->willReturnMap($option['expected']);
                        break;
                    case 'readServiceTemplateRepository':
                        $testCase->readServiceTemplateRepository
                            ->expects($testCase->once())
                            ->method($option['method'])
                            ->with($option['arguments'])
                            ->willReturn($option['expected']);
                        break;
                    case 'serviceSeverityRepository':
                        $testCase->serviceSeverityRepository
                            ->expects($testCase->once())
                            ->method('exists')
                            ->with($option['arguments'])
                            ->willReturn($option['expected']);
                        break;
                    case 'performanceGraphRepository':
                        $testCase->performanceGraphRepository
                            ->expects($testCase->once())
                            ->method('exists')
                            ->with($option['arguments'])
                            ->willReturn($option['expected']);
                        break;
                    case 'commandRepository':
                        $testCase->commandRepository
                            ->expects($testCase->once())
                            ->method($option['method'])
                            ->with($option['arguments'])
                            ->willReturn($option['expected']);
                        break;
                    case 'timePeriodRepository':
                        $testCase->timePeriodRepository
                            ->expects($testCase->exactly(count($option['expected'])))
                            ->method('exists')
                            ->will($testCase->returnValueMap($option['expected']));
                        break;
                    case 'imageRepository':
                        $testCase->imageRepository
                            ->expects($testCase->once())
                            ->method('existsOne')
                            ->with($option['arguments'])
                            ->willReturn($option['expected']);
                        break;
                    case 'writeServiceTemplateRepository':
                        $testCase->writeServiceTemplateRepository
                            ->expects($testCase->once())
                            ->method('add')
                            ->willReturn($option['expected']);
                        break;
                    case 'readServiceMacroRepository':
                        $testCase->readServiceMacroRepository
                            ->expects($testCase->exactly(count($option['expected'])))
                            ->method($option['method'])
                            ->will($testCase->returnValueMap($option['expected']));
                        break;
                    case 'readCommandMacroRepository':
                        $testCase->readCommandMacroRepository
                            ->expects($testCase->once())
                            ->method($option['method'])
                            ->with(...$option['arguments'])
                            ->willReturn($option['expected']);
                        break;
                    case 'writeServiceMacroRepository':
                        $testCase->writeServiceMacroRepository
                            ->expects($testCase->exactly(count($option['expected'])))
                            ->method($option['method'])
                            ->will($testCase->returnValueMap($option['expected']));
                        break;
                }
            }
        }
    }
}
