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

final class SandboxException extends \Exception
{
    public static function unableToInstanciate(): self
    {
        return new self('Unable to instanciate FileSandbox');
    }

    public static function unableToCreateDirectory(): self
    {
        return new self('Unable to create sandbox directory');
    }

    public static function directoryNotWritable(): self
    {
        return new self('Sandbox directory is not writable');
    }

    public static function unableToRemoveDirectory(): self
    {
        return new self('Unable to delete sandbox directory');
    }

    public static function fileNotFound(string $filePath): self
    {
        return new self(sprintf('File [%s] not found', $filePath));
    }

    public static function invalidFileName(string $ileName): self
    {
        return new self(sprintf('File name [%s] is not valid'));
    }

    public static function unsupportedFileTypeEnum(): self
    {
        return new self('Unsupported FileTypeEnum');
    }

    public static function unableToCopyFile(): self
    {
        return new self('Unable to copy file');
    }

    public static function invalidMimeType(string $mimeType): self
    {
        return new self(sprintf('Mime Type [%s] is invalid'));
    }

    public static function MimeTypeDoesNotMatchFileExtension(string $mimeType, string $extension): self
    {
        return new self(sprintf('Mime Type [%s] does not match [%s] file extension', [$mimeType, $extension]));
    }

    public static function unableToReadUploadedFile(string $filename): self
    {
        return new self(sprintf('Unable to read file [%s]', $filename));
    }

    public static function unableToWriteFile(string $filename): self
    {
        return new self(sprintf('Unable to Write file [%s] in the sandbox', $filename));
    }

    public static function unableToChangePermissions(
        int $newPermission,
        string $filename
    ): self {
        return new self(sprintf(
            'Unable to set permissions [%d] on file [%s] in the sandbox',
            [$newPermission, $filename]
        ));
    }

    public static function InvalidArchiveDepth(int $depth, int $allowedDepth): self
    {
        return new self(sprintf(
            'Archive depth is not valid. Depth is [%d], only [%d] allowed',
            [$depth, $allowedDepth]
        ));
    }

    public static function InvalidFileContent(): self
    {
        return new self('Invalid File Content');
    }
}
