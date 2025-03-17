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

namespace Core\Common\Domain\Security;

use Centreon\Domain\Log\LoggerTrait;
use enshrined\svgSanitize\Sanitizer;
use PharData;
use Random\RandomException;
use SplFileInfo;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

final class FileSandbox
{
    use LoggerTrait;

    private const DIR_PERM = 0700;
    private const FILE_PERM = 0600;
    private const MAXIMUM_ARCHIVE_DEPTH = 1;

    // File Name Validation Patterns
    private const ALLOWED_IMAGE_CHAR_PATTERN = '^(?!.*\.\.\/)[\w\-_.]+\.(jpg|jpeg|png|gif|svg)$';
    private const ALLOWED_ARCHIVE_CHAR_PATTERN = '^(?!.*\.\.\/)[\w\-_.]+\.(zip|rar|tar|tgz|gz)$';

    // Mime Type
    private const MIME_TYPE_JPEG = 'image/jpeg';
    private const MIME_TYPE_PNG = 'image/png';
    private const MIME_TYPE_GIF = 'image/gif';
    private const MIME_TYPE_SVG = 'image/svg+xml';
    private const MIME_TYPE_XGZIP = 'application/x-gzip';
    private const MIME_TYPE_ZIP = 'application/zip';

    private const ALLOWED_MIME_TYPES = [
        self::MIME_TYPE_JPEG,
        self::MIME_TYPE_PNG,
        self::MIME_TYPE_GIF,
        self::MIME_TYPE_SVG,
        self::MIME_TYPE_XGZIP,
        self::MIME_TYPE_ZIP,
    ];

    // Extension
    private const JPG_EXTENSION = 'jpg';
    private const JPEG_EXTENSION = 'jpeg';
    private const PNG_EXTENSION = 'png';
    private const GIF_EXTENSION = 'gif';
    private const SVG_EXTENSION = 'svg';
    private const ZIP_EXTENSION = 'zip';
    private const GZ_EXTENSION = 'gz';
    private const TGZ_EXTENSION = 'tgz';

    private const MIME_TYPE_EXTENSION_CONCORDANCE = [
        self::JPG_EXTENSION => self::MIME_TYPE_JPEG,
        self::JPEG_EXTENSION => self::MIME_TYPE_JPEG,
        self::PNG_EXTENSION => self::MIME_TYPE_PNG,
        self::GIF_EXTENSION => self::MIME_TYPE_GIF,
        self::SVG_EXTENSION => self::MIME_TYPE_SVG,
        self::ZIP_EXTENSION => self::MIME_TYPE_ZIP,
        self::GZ_EXTENSION => self::MIME_TYPE_XGZIP,
        self::TGZ_EXTENSION => self::MIME_TYPE_XGZIP,
    ];

    private Filesystem $fileSystem;
    private Sanitizer $svgSanitizer;

    private string $sandboxDirectory;
    private ?string $originalFilePath = null;

    /**
     *
     * @param FileTypeEnum $type
     * @param null|string $sandboxRootPath
     *
     * @throws RandomException
     */
    private function __construct(private readonly FileTypeEnum $type, private ?string $sandboxRootPath = null) {
        try {
            $this->sandboxRootPath ??= sys_get_temp_dir();
            $this->sandboxDirectory = $this->sandboxRootPath .  DIRECTORY_SEPARATOR
                . 'sandbox_' . bin2hex(random_bytes(8));
            $this->fileSystem = new Filesystem();
            $this->svgSanitizer = new Sanitizer();
        } catch (RandomException $ex) {
            $this->error(
                message: 'Unable to instanciate sandbox directory',
                context: [
                    'exception' => $ex,
                ]
            );

            throw SandboxException::unableToInstanciate();
        }
    }

    /**
     * @throws SandboxException
     */
    public function __destruct()
    {
        $this->removeDirectory();
    }

    /**
     * Factory to instanciate FileSandbox.
     *
     * @param string|null $sandboxRootPath
     *
     * @return self
     *
     * @throws SandboxException
     */
    public static function create(?string $sandboxRootPath = null, FileTypeEnum $type): self
    {
        $sandbox = new self($type, $sandboxRootPath);
        $sandbox->createDirectory();

        return $sandbox;
    }

    /**
     * Remove the sandbox directory.
     *
     * @throws SandboxException
     */
    private function removeDirectory(): void
    {
        $this->info(
            message: 'Deleting Sandbox Directory',
            context: [
                'sandbox_dir' => $this->sandboxDirectory,
            ]
        );

        try {
            if ($this->fileSystem->exists($this->sandboxDirectory)) {
                $this->fileSystem->remove($this->sandboxDirectory);
            }
        } catch (IOException $ex) {
            $this->error(
                message: 'Unable to delete sandbox directory, you should remove it manually',
                context: [
                    'sandbox_dir' => $this->sandboxDirectory,
                    'exception' => $ex,
                ]
            );

            throw SandboxException::unableToRemoveDirectory();
        }
    }

    /**
     * Analyse the file to ensure it is safe to use.
     *
     *  - Check file name is valid and not contains directory traversal.
     *  - Check file mime type is allowed and is coherent with the file extension.
     *  - Check file content is valid and does not contain any malicious.
     *
     * @param SplFileInfo $file
     *
     * @throws IOException
     * @throws SandboxException
     */
    public function analyse(\SplFileInfo $file): void
    {
        try {
            if ($file->getRealPath() === false) {
                throw SandboxException::fileNotFound($file->getPath() . DIRECTORY_SEPARATOR . $file->getFilename());
            }
            $perms = $file->getPerms();
            $this->validateFileName($file->getRealPath());
            $sandboxFile = $this->copyFile($file);
            $this->validateMimeType($sandboxFile);
            $this->validateFileContent($sandboxFile);

            // Restore Permissions as the analyse is over.
            $this->fileSystem->chmod($file->getRealPath(), $perms);
        } catch (SandboxException|IOException $ex) {
            $this->error(
                message: 'An error occurs during file analyse. '
                    . 'The uploaded file will be remove, as the sandbox directory',
                context: [
                    'file_path' => $file->getRealPath(),
                    'sandbox_directory' => $this->sandboxRootPath,
                    'exception' => $ex]
            );

            $this->fileSystem->remove($file->getRealPath());
            $this->removeDirectory();
        }
    }

    /**
     * Create a directory with RWX permissions for user to analyse the given file.
     *
     * @throws SandboxException
     */
    private function createDirectory(): void
    {
        $fileSystem = new Filesystem();
        $this->info(
            message: 'Creating Sandbox Directory',
            context: [
                'sandbox_dir' => $this->sandboxDirectory,
            ]
        );

        try {
            $fileSystem->mkdir($this->sandboxDirectory, self::DIR_PERM);
        } catch (IOException $ex) {
            $this->error(
                message: 'Unable to create sandbox directory with permissions or directory is not writable',
                context: [
                    'permissions' => self::DIR_PERM,
                    'exception' => $ex,
                ]
            );

            throw SandboxException::unableToCreateDirectory();
        }

        if (! is_writable($this->sandboxDirectory)) {
            throw SandboxException::directoryNotWritable();
        }

        $this->info('Sandbox directory created');
    }

    /**
     * Copy the file to the sandbox.
     *
     *  - Ensure file is not executable in the sandbox.
     *  - Set original file to read only.
     *
     * @param SplFileInfo $file
     * @return SplFileInfo
     * @throws SandboxException
     */
    private function copyFile(\SplFileInfo $file): \SplFileInfo
    {
        try {
            $this->info(
                message: 'Copying file to sandbox',
                context: [
                    'file_original_location' => $file->getRealPath(),
                    'file_destination' => $this->sandboxDirectory
                ],
            );
            $sandboxFilePath = $this->sandboxDirectory . DIRECTORY_SEPARATOR . $file->getFilename();
            $this->fileSystem->copy($file->getRealPath(), $sandboxFilePath);
            // Ensure file is not executable in the sandbox.
            $this->fileSystem->chmod($sandboxFilePath, self::FILE_PERM);
            // Set original file to read only.
            $this->fileSystem->chmod($file->getRealPath(), self::FILE_PERM);

            return new SplFileInfo($sandboxFilePath);

        } catch (IOException $ex) {
            $this->error(
                message: 'Unable to copy file',
                context: [
                    'file_original_location' => $file->getRealPath(),
                    'file_destination' => $this->sandboxDirectory,
                    'exception' => $ex,
                ]
            );

            throw SandboxException::unableToCopyFile();
        }
    }

    private function validateFileName(string $filePath): void
    {
        $pattern = match ($this->type) {
            FileTypeEnum::TYPE_IMAGE => '/' . self::ALLOWED_IMAGE_CHAR_PATTERN . '/',
            FileTypeEnum::TYPE_ARCHIVE => '/' . self::ALLOWED_ARCHIVE_CHAR_PATTERN . '/',
            default => throw SandboxException::unsupportedFileTypeEnum()
        };

        if (! preg_match($pattern, $filePath)) {
            throw SandboxException::invalidFileName($filePath);
        }
    }

    private function validateMimeType(\SplFileInfo $file): void
    {
        $mimeType = mime_content_type($file->getRealPath());
        if (! in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            throw SandboxException::invalidMimeType($mimeType);
        }
        if (self::MIME_TYPE_EXTENSION_CONCORDANCE[$file->getExtension()] !== $mimeType) {
            throw SandboxException::MimeTypeDoesNotMatchFileExtension($mimeType, $file->getExtension());
        }
    }

    private function validateFileContent(\SplFileInfo $file): void
    {
        $mimeType = mime_content_type($file->getRealPath());

        if (in_array($mimeType, [self::MIME_TYPE_ZIP, self::MIME_TYPE_XGZIP])) {
            $archive = new PharData($file->getRealPath());
            $archive->rewind();
            $archiveIterator = new \RecursiveIteratorIterator($archive);

            /** @var \PharFileInfo $file */
            foreach ($archiveIterator as $file) {
                if (($depth = $archiveIterator->getDepth()) >= self::MAXIMUM_ARCHIVE_DEPTH) {
                    throw SandboxException::InvalidArchiveDepth($depth, self::MAXIMUM_ARCHIVE_DEPTH);
                }
                $this->validateFileName($file->getFilename());
                $this->validateMimeType($file);
            }

            return;
        }

        $isImageValid = match ($mimeType) {
            self::MIME_TYPE_JPEG => @imagecreatefromjpeg($file->getRealPath()),
            self::MIME_TYPE_GIF => @imagecreatefromgif($file->getRealPath()),
            self::MIME_TYPE_PNG => @imagecreatefrompng($file->getRealPath()),
            self::MIME_TYPE_SVG => (bool) $this->svgSanitizer->sanitize(file_get_contents($file->getRealPath())) 
        };

        if ($isImageValid === false) {
            throw SandboxException::InvalidFileContent();
        }
    }
}
