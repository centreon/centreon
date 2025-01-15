<?php
/*
 * Copyright 2005-2019 Centreon
 * Centreon is developed by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 *
 */

namespace Centreon\Tests\Domain\Service;

use PHPUnit\Framework\TestCase;
use Centreon\Test\Mock;
use Centreon\Domain\Service\I18nService;
use CentreonLegacy\Core\Module\Information;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
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
        'centreon-license-manager' => []
    ];

    /**
     * @return void
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
        $path = "/usr/share/centreon/www/locale/fr_FR.UTF-8/LC_MESSAGES/messages.ser";
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
     * @return void
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public function testGetTranslation(): void
    {
        $result = $this->translation->getTranslation();

        $this->assertTrue(is_array($result));
        $this->assertArrayHasKey('en', $result);
        $this->assertArrayHasKey('fr', $result);
    }
}
