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

namespace Centreon\Tests\Domain\Service;

use Centreon\Domain\Service\I18nService;
use CentreonLegacy\Core\Module\Information;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Class
 *
 * @class I18nTest
 * @package Centreon\Tests\Domain\Service
 */
class I18nTest extends TestCase
{
    /** @var I18nService */
    public $translation;

    /** @var array[] */
    private $installedList = [
        'centreon-license-manager' => [],
    ];

    /**
     * @throws \PHPUnit\Framework\InvalidArgumentException
     * @throws \PHPUnit\Framework\MockObject\ClassAlreadyExistsException
     * @throws \PHPUnit\Framework\MockObject\ClassIsFinalException
     * @throws \PHPUnit\Framework\MockObject\ClassIsReadonlyException
     * @throws \PHPUnit\Framework\MockObject\DuplicateMethodException
     * @throws \PHPUnit\Framework\MockObject\IncompatibleReturnValueException
     * @throws \PHPUnit\Framework\MockObject\InvalidMethodNameException
     * @throws \PHPUnit\Framework\MockObject\OriginalConstructorInvocationRequiredException
     * @throws \PHPUnit\Framework\MockObject\ReflectionException
     * @throws \PHPUnit\Framework\MockObject\RuntimeException
     * @throws \PHPUnit\Framework\MockObject\UnknownTypeException
     * @return void
     */
    protected function setUp(): void
    {
        $moduleInformationMock = $this->getMockBuilder(Information::class)
            ->disableOriginalConstructor()
            ->getMock();
        $moduleInformationMock->method('getInstalledList')->willReturn($this->installedList);

        $splFileInfoMock = $this->getMockBuilder(SplFileInfo::class)
            ->disableOriginalConstructor()
            ->getMock();
        $splFileInfoMock->method('getContents')->willReturn(
            'a:2:{s:2:"en";a:1:{s:16:"Discovered Items";s:16:"Discovered Items";}'
            . 's:2:"fr";a:1:{s:16:"Discovered Items";s:21:"Eléments découverts";}}'
        );
        $finderMock = $this->getMockBuilder(Finder::class)
            ->disableOriginalConstructor()
            ->getMock();
        $path = '/usr/share/centreon/www/locale/fr_FR.UTF-8/LC_MESSAGES/messages.ser';
        $finderMock->method('name')->willReturn($finderMock);
        $finderMock->method('in')->willReturn($finderMock);
        $finderMock->method('getIterator')->willReturn(new \ArrayIterator([$splFileInfoMock]));

        $filesystemMock = $this->getMockBuilder(Filesystem::class)
            ->disableOriginalConstructor()
            ->getMock();
        $filesystemMock->method('exists')->willReturn(true);

        $this->translation = new I18nService($moduleInformationMock, $finderMock, $filesystemMock);
    }

    /**
     * @return void
     */
    public function tearDown(): void
    {
    }

    /**
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @return void
     */
    public function testGetTranslation(): void
    {
        $result = $this->translation->getTranslation();

        $this->assertTrue(is_array($result));
        $this->assertArrayHasKey('en', $result);
        $this->assertArrayHasKey('fr', $result);
    }
}
