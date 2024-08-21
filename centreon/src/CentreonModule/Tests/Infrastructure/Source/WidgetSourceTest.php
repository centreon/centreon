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

namespace CentreonModule\Tests\Infrastructure\Source;

use Centreon\Test\Mock;
use Centreon\Test\Traits\TestCaseExtensionTrait;
use CentreonLegacy\Core\Configuration\Configuration;
use CentreonModule\Infrastructure\Entity\Module;
use CentreonModule\Infrastructure\Source\WidgetSource;
use CentreonModule\Tests\Resources\Traits\SourceDependencyTrait;
use PHPUnit\Framework\TestCase;
use Pimple\Container;
use Pimple\Psr11\Container as ContainerWrap;
use Symfony\Component\Finder\Finder;
use VirtualFileSystem\FileSystem;

class WidgetSourceTest extends TestCase
{
    use TestCaseExtensionTrait;
    use SourceDependencyTrait;

    /** @var string */
    public static $widgetName = 'test-widget';

    /** @var string[] */
    public static $widgetInfo = [
        'title' => 'Curabitur congue porta neque',
        'author' => 'Centreon',
        'email' => 'centreon@mail.loc',
        'website' => 'localhost',
        'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Praesent id ante neque.',
        'version' => 'x.y.q',
        'keywords' => 'lorem,ipsum,dolor',
        'stability' => 'release candidate',
        'last_update' => '2011-11-11',
        'release_note' => 'https://github.com/centreon/centreon-dummy/releases',
        'screenshot1' => './resources/screenshot1.png',
        'screenshot2' => './resources/screenshot2.png',
        'screenshot3' => './resources/screenshot3.png',
        'screenshot4' => './resources/screenshot4.png',
        'thumbnail' => './resources/thumbnail.png',
        'url' => './widgets/test-widget/index.php',
    ];

    /** @var string[][][] */
    public static $sqlQueryVsData = [
        'SELECT `directory` AS `id`, `version` FROM `widget_models`' => [
            [
                'id' => 'test-widget',
                'version' => 'x.y.z',
            ],
        ],
    ];

    /** @var FileSystem */
    private $fs;

    /** @var WidgetSource */
    private $source;

    protected function setUp(): void
    {
        // mount VFS
        $this->fs = new FileSystem();
        $this->fs->createDirectory('/widgets');
        $this->fs->createDirectory('/widgets/' . static::$widgetName);
        $this->fs->createFile(
            '/widgets/' . static::$widgetName . '/' . WidgetSource::CONFIG_FILE,
            static::buildConfContent(),
        );

        // provide services
        $container = new Container();
        $container['finder'] = new Finder();
        $container['configuration'] = $this->createMock(Configuration::class);

        // DB service
        $container[\Centreon\ServiceProvider::CENTREON_DB_MANAGER] = new Mock\CentreonDBManagerService();
        foreach (static::$sqlQueryVsData as $query => $data) {
            $container[\Centreon\ServiceProvider::CENTREON_DB_MANAGER]->addResultSet($query, $data);
        }

        $this->setUpSourceDependency($container);

        $containerWrap = new ContainerWrap($container);

        $this->source = $this->getMockBuilder(WidgetSource::class)
            ->onlyMethods([
                'getPath',
            ])
            ->setConstructorArgs([
                $containerWrap,
            ])
            ->getMock();
        $this->source
            ->method('getPath')
            ->will($this->returnCallback(fn() => $this->fs->path('/widgets/')));
    }

    public function tearDown(): void
    {
    }

    public function testGetList(): void
    {
        $result = $this->source->getList();

        $this->assertTrue(is_array($result));

        $result2 = $this->source->getList('missing-widget');
        $this->assertEquals([], $result2);
    }

    public function testGetDetail(): void
    {
        (function (): void {
            $result = $this->source->getDetail('missing-widget');

            $this->assertNull($result);
        })();

        (function (): void {
            $result = $this->source->getDetail(static::$widgetName);

            $this->assertInstanceOf(Module::class, $result);
        })();
    }

    public function testCreateEntityFromConfig(): void
    {
        $configFile = $this->getConfFilePath();
        $result = $this->source->createEntityFromConfig($configFile);

        $this->assertInstanceOf(Module::class, $result);
        $this->assertEquals(static::$widgetName, $result->getId());
        $this->assertEquals(WidgetSource::TYPE, $result->getType());
        $this->assertEquals(static::$widgetInfo['title'], $result->getName());
        $this->assertEquals(static::$widgetInfo['author'], $result->getAuthor());
        $this->assertEquals(static::$widgetInfo['version'], $result->getVersion());
        $this->assertEquals(static::$widgetInfo['keywords'], $result->getKeywords());
        $this->assertTrue($result->isInstalled());
        $this->assertFalse($result->isUpdated());
    }

    public static function buildConfContent(): string
    {
        $widgetInfo = static::$widgetInfo;
        $result = <<<CONF
            <configs>
                <title>{$widgetInfo['title']}</title>
                <author>{$widgetInfo['author']}</author>
                <email>{$widgetInfo['email']}</email>
                <website>{$widgetInfo['website']}</website>
                <description>{$widgetInfo['description']}</description>
                <version>{$widgetInfo['version']}</version>
                <keywords>{$widgetInfo['keywords']}</keywords>
                <stability>{$widgetInfo['stability']}</stability>
                <last_update>{$widgetInfo['last_update']}</last_update>
                <release_note>{$widgetInfo['release_note']}</release_note>
                <screenshot>{$widgetInfo['screenshot1']}</screenshot>
                <screenshot>{$widgetInfo['screenshot2']}</screenshot>
                <screenshots>
                    <screenshot src="{$widgetInfo['screenshot4']}"/>
                </screenshots>
                <thumbnail>{$widgetInfo['thumbnail']}</thumbnail>
                <url>{$widgetInfo['url']}</url>
            </configs>
            CONF;

        return $result;
    }

    private function getConfFilePath(): string
    {
        return $this->fs->path('/widgets/' . static::$widgetName . '/' . WidgetSource::CONFIG_FILE);
    }
}
