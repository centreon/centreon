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

declare(strict_types = 1);

namespace Core\Common\Infrastructure\Repository;

use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Security\Encryption;

class FileDataStoreEngine implements DataStorageEngineInterface
{
    private bool $inTransaction = false;

    private string $workingDirectory = '';

    private string $lastError = '';

    /**
     * @param string $absoluteMediaPath Absolute destination path for the media
     * @param bool $throwsException Indicates whether an exception should be thrown in the event of an error,
     *                              false by default
     *
     * @throws \Exception
     */
    public function __construct(private string $absoluteMediaPath, private bool $throwsException = false)
    {
        $this->absoluteMediaPath = realpath($absoluteMediaPath)
            ?: throw new \Exception(sprintf('Path invalid \'%s\'', $absoluteMediaPath));
    }

    /**
     * Defines whether an exception should be thrown in the event of an error.
     *
     * @param bool $throwsException
     */
    public function throwsException(bool $throwsException): void
    {
        $this->throwsException = $throwsException;
    }

    /**
     * @inheritDoc
     */
    public function rollbackTransaction(): bool
    {
        try {
            $rollbackStatus = $this->rollbackFileSystem();
            $this->inTransaction = false;

            return $rollbackStatus;
        } catch (\Throwable $ex) {
            $this->lastError = $ex->getMessage();
            if ($this->throwsException) {
                throw $ex;
            }

            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function startTransaction(): bool
    {
        if ($this->inTransaction) {
            return false;
        }
        $tempDirectory = str_replace('/', '-', Encryption::generateRandomString(32));
        $this->workingDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $tempDirectory;
        if (! mkdir($this->workingDirectory)) {
            throw new \Exception(
                sprintf('Unable to create a directory \'%s\'', $this->workingDirectory)
            );
        }
        $this->inTransaction = true;

        return true;
    }

    /**
     * @inheritDoc
     */
    public function commitTransaction(): bool
    {
        $this->inTransaction = false;

        return $this->commitFileSystem();
    }

    /**
     * @inheritDoc
     */
    public function isAlreadyinTransaction(): bool
    {
        return $this->inTransaction;
    }

    /**
     * @param string $pathname
     * @param string $data
     * @param int $flags
     *
     * @throws \Exception
     *
     * @return int|false
     */
    public function addFile(string $pathname, string $data, int $flags = 0): int|false
    {
        $absolutePathName = $this->workingDirectory . DIRECTORY_SEPARATOR . $pathname;
        $filePath = pathinfo($absolutePathName);
        if (empty($filePath['dirname'])) {
            throw new \Exception('The destination directory cannot be the root');
        }
        if (! is_dir($filePath['dirname'])) {
            $this->createDirectory($filePath['dirname']);
        }

        return file_put_contents($absolutePathName, $data, $flags);
    }

    /**
     * @param string $filePath
     *
     * @throws \Exception
     */
    public function deleteFromFileSystem(string $filePath): void
    {
        $this->deleteFile($this->absoluteMediaPath . DIRECTORY_SEPARATOR . $filePath);
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    /**
     * Copies files from the temporary working folder to the destination folder.
     *
     * @throws \Exception
     *
     * @return bool
     */
    private function commitFileSystem(): bool
    {
        try {
            $fromDirIterator = new \RecursiveDirectoryIterator($this->workingDirectory, \FilesystemIterator::SKIP_DOTS);
            /** @var iterable<\SplFileInfo> $files */
            $files = new \RecursiveIteratorIterator($fromDirIterator, \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $destDirectory = $this->absoluteMediaPath . DIRECTORY_SEPARATOR . $fromDirIterator->getFilename();
                if (! is_dir($destDirectory)) {
                    $this->createDirectory($destDirectory);
                }
                if (! $file->isDir()) {
                    $destFile = $destDirectory . DIRECTORY_SEPARATOR . $file->getFilename();
                    $this->copyFile($file->getPathname(), $destFile);
                }
            }
            $this->recursivelyDeleteDirectory($this->workingDirectory);

            return true;
        } catch (\Exception $ex) {
            $this->lastError = $ex->getMessage();
            if ($this->throwsException) {
                throw $ex;
            }

            return false;
        }
    }

    /**
     * @param string $from
     * @param string $destination
     *
     * @throws \Exception
     */
    private function copyFile(string $from, string $destination): void
    {
        if (! copy($from, $destination)) {
            throw new \Exception(
                sprintf('Unable to copy file from \'%s\' to \'%s\'', $from, $destination)
            );
        }
    }

    /**
     * @param string $path
     *
     * @throws \Exception
     */
    private function createDirectory(string $path): void
    {
        if (! mkdir($path)) {
            throw new \Exception(sprintf('Unable to create a directory \'%s\'', $path));
        }
    }

    /**
     * @param string $path
     *
     * @throws \Exception
     */
    private function deleteDirectory(string $path): void
    {
        if (! rmdir($path)) {
            throw new \Exception(sprintf('Unable to delete the directory \'%s\'', $path));
        }
    }

    /**
     * @param string $filepath
     *
     * @throws \Exception
     */
    private function deleteFile(string $filepath): void
    {
        if (! unlink($filepath)) {
            throw new \Exception(sprintf('Unable to delete the file \'%s\'', $filepath));
        }
    }

    /**
     * Deletes all files in the temporary working folder.
     *
     * @throws \Exception Indicates whether an exception should be thrown in the event of an error, false by default
     *
     * @return bool
     */
    private function rollbackFileSystem(): bool
    {
        try {
            $this->recursivelyDeleteDirectory($this->workingDirectory);

            return true;
        } catch (\Throwable $ex) {
            $this->lastError = $ex->getMessage();
            if ($this->throwsException) {
                throw $ex;
            }

            return false;
        }
    }

    /**
     * Recursively delete the directory and its contents.
     *
     * @param string $path
     *
     * @throws \Exception
     */
    private function recursivelyDeleteDirectory(string $path): void
    {
        $dirIterator = new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS);
        /** @var iterable<\SplFileInfo> $files */
        $files = new \RecursiveIteratorIterator($dirIterator, \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isDir()){
                $this->deleteDirectory($file->getRealPath());
            } else {
                $this->deleteFile($file->getRealPath());
            }
        }
        $this->deleteDirectory($this->workingDirectory);
    }
}
