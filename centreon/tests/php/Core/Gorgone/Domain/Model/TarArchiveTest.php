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

namespace Tests\Core\Gorgone\Domain\Model;

use Core\Gorgone\Domain\Model\TarArchive;

/**
 * Read an archive back with PharData, an implementation independent from the writer.
 *
 * @return array<string, array{content: string, isFile: bool, mtime: int}>
 */
function readTarArchive(string $archive): array
{
    $path = sys_get_temp_dir() . '/' . uniqid('gorgone-tar-', true) . '.tar';
    file_put_contents($path, $archive);

    try {
        $entries = [];
        $phar = new \PharData($path);
        /** @var \PharFileInfo $entry */
        foreach (new \RecursiveIteratorIterator($phar) as $entry) {
            $name = mb_substr($entry->getPathname(), mb_strlen('phar://' . $path . '/'));
            $entries[$name] = [
                'content' => $entry->getContent(),
                'isFile' => $entry->isFile() && ! $entry->isLink(),
                'mtime' => $entry->getMTime(),
            ];
        }
        unset($phar);

        return $entries;
    } finally {
        unlink($path);
    }
}

it('should build a tar archive of regular files with relative names', function (): void {
    $files = [
        'centengine.cfg' => "cfg_file=/etc/centreon-engine/hosts.cfg\n",
        'hosts.cfg' => str_repeat('define host { }' . "\n", 100),
        'empty.cfg' => '',
        'exactly-one-block.cfg' => str_repeat('a', 512),
    ];
    $archive = new TarArchive($files);
    $content = $archive->toString(1700000000);

    expect($archive->isEmpty())->toBeFalse()
        ->and($archive->getFileNames())->toBe(array_keys($files))
        ->and(mb_strlen($content, '8bit') % 512)->toBe(0)
        ->and(mb_substr($content, -1024, null, '8bit'))->toBe(str_repeat("\0", 1024));

    $entries = readTarArchive($content);
    // PharData lists the entries in alphabetical order.
    expect(array_keys($entries))->toEqualCanonicalizing(array_keys($files));
    foreach ($files as $name => $expected) {
        expect($entries[$name]['content'])->toBe($expected)
            ->and($entries[$name]['isFile'])->toBeTrue()
            ->and($entries[$name]['mtime'])->toBe(1700000000);
    }
});

it('should write ustar headers without leading directory', function (): void {
    $content = (new TarArchive(['centreontrapd.sdb' => 'SQLite format 3']))->toString(0);

    expect(rtrim(mb_substr($content, 0, 100, '8bit'), "\0"))->toBe('centreontrapd.sdb')
        ->and(mb_substr($content, 156, 1, '8bit'))->toBe('0')
        ->and(mb_substr($content, 257, 5, '8bit'))->toBe('ustar')
        ->and(mb_substr($content, 512, 15, '8bit'))->toBe('SQLite format 3')
        ->and(mb_strlen($content, '8bit'))->toBe(512 * 4);
});

it('should count the sizes in bytes', function (): void {
    $files = [
        'accents.cfg' => "alias Serveur de météo à Besançon – 日本\n",
        'binary.sdb' => random_bytes(1500) . "\xff\xfe\x00",
        'répertoire/hôte.cfg' => 'é',
    ];
    $content = (new TarArchive($files))->toString();

    expect(mb_strlen($content, '8bit') % 512)->toBe(0);

    $entries = readTarArchive($content);
    expect(array_keys($entries))->toEqualCanonicalizing(array_keys($files));
    foreach ($files as $name => $expected) {
        expect($entries[$name]['content'])->toBe($expected);
    }
});

it('should store a long name in the prefix field', function (): void {
    $name = str_repeat('d', 90) . '/' . str_repeat('f', 60) . '.json';
    $entries = readTarArchive((new TarArchive([$name => '{}']))->toString());

    expect($entries)->toHaveKey($name)
        ->and($entries[$name]['content'])->toBe('{}');
});

it('should build an empty archive', function (): void {
    $archive = new TarArchive();

    expect($archive->isEmpty())->toBeTrue()
        ->and($archive->toString())->toBe(str_repeat("\0", 1024));
});

it('should refuse a name which is not a relative file name', function (string $name): void {
    new TarArchive([$name => 'content']);
})->with([
    [''],
    ['/etc/passwd'],
    ['../outside.cfg'],
    ['dir/../../outside.cfg'],
    ['./file.cfg'],
    ['dir/'],
    ['dir//file.cfg'],
    ["file\0.cfg"],
])->throws(\InvalidArgumentException::class);

it('should refuse a name too long for the ustar format', function (): void {
    new TarArchive([str_repeat('f', 101) => 'content']);
})->throws(\InvalidArgumentException::class, 'too long');
