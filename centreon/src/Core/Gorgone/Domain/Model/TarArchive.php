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

namespace Core\Gorgone\Domain\Model;

/**
 * Uncompressed ustar archive holding regular files with relative names, built in memory.
 *
 * Sizes and offsets are counted in bytes: the string functions use the "8bit" encoding.
 */
final class TarArchive
{
    private const BLOCK_SIZE = 512;
    private const NAME_MAX_LENGTH = 100;
    private const PREFIX_MAX_LENGTH = 155;
    private const FILE_MODE = 0644;
    private const BYTES = '8bit';

    /** @var array<string, string> */
    private array $files = [];

    /**
     * @param array<string, string> $files content of the files, indexed by their relative name
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(array $files = [])
    {
        foreach ($files as $name => $content) {
            $this->addFile((string) $name, $content);
        }
    }

    /**
     * @param string $name
     * @param string $content
     *
     * @throws \InvalidArgumentException
     */
    public function addFile(string $name, string $content): void
    {
        self::splitName($name);
        $this->files[$name] = $content;
    }

    public function isEmpty(): bool
    {
        return $this->files === [];
    }

    /**
     * @return list<string>
     */
    public function getFileNames(): array
    {
        return array_keys($this->files);
    }

    /**
     * @param int|null $modificationTime Unix time written in every entry (now by default)
     *
     * @return string
     */
    public function toString(?int $modificationTime = null): string
    {
        $modificationTime ??= time();
        $archive = '';
        foreach ($this->files as $name => $content) {
            $size = mb_strlen($content, self::BYTES);
            $archive .= self::buildHeader((string) $name, $size, $modificationTime) . $content;
            $padding = (self::BLOCK_SIZE - $size % self::BLOCK_SIZE) % self::BLOCK_SIZE;
            $archive .= str_repeat("\0", $padding);
        }

        // End of archive: two zero blocks.
        return $archive . str_repeat("\0", 2 * self::BLOCK_SIZE);
    }

    private static function buildHeader(string $name, int $size, int $modificationTime): string
    {
        [$prefix, $baseName] = self::splitName($name);

        $header = pack(
            'a100a8a8a8a12a12a8a1a100a6a2a32a32a8a8a155a12',
            $baseName,
            sprintf('%07o', self::FILE_MODE),
            sprintf('%07o', 0),
            sprintf('%07o', 0),
            sprintf('%011o', $size),
            sprintf('%011o', $modificationTime),
            str_repeat(' ', 8),
            '0',
            '',
            'ustar',
            '00',
            '',
            '',
            '',
            '',
            $prefix,
            ''
        );

        $checksum = array_sum(array_map(ord(...), mb_str_split($header, 1, self::BYTES)));

        return substr_replace($header, sprintf("%06o\0 ", $checksum), 148, 8);
    }

    /**
     * Validate a name and split it into the ustar "prefix" and "name" fields.
     *
     * @param string $name
     *
     * @throws \InvalidArgumentException
     *
     * @return array{string, string}
     */
    private static function splitName(string $name): array
    {
        if (
            $name === ''
            || str_starts_with($name, '/')
            || str_ends_with($name, '/')
            || str_contains($name, "\0")
            || array_intersect(explode('/', $name), ['', '.', '..']) !== []
        ) {
            throw new \InvalidArgumentException(
                sprintf('"%s" is not a relative file name', $name)
            );
        }

        if (mb_strlen($name, self::BYTES) <= self::NAME_MAX_LENGTH) {
            return ['', $name];
        }

        $offset = 0;
        while (($position = mb_strpos($name, '/', $offset, self::BYTES)) !== false) {
            if (
                $position <= self::PREFIX_MAX_LENGTH
                && mb_strlen($name, self::BYTES) - $position - 1 <= self::NAME_MAX_LENGTH
            ) {
                return [
                    mb_substr($name, 0, $position, self::BYTES),
                    mb_substr($name, $position + 1, null, self::BYTES),
                ];
            }
            $offset = $position + 1;
        }

        throw new \InvalidArgumentException(
            sprintf('File name "%s" is too long for a tar archive', $name)
        );
    }
}
