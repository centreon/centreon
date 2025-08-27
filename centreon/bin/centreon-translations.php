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

if (strlen($argv[1]) !== 2) {
    exit(
        sprintf("The length of the language code must be 2\n")
    );
}

$languageCode = strtolower($argv[1]);

if ($argc === 3 || $argc === 4) {
    if (!file_exists($argv[2])) {
        exit(
            sprintf("Translation file '%s' does not exist\n", $argv[2])
        );
    }
} else {
    $currentFileInfos = pathinfo(__FILE__);
    $execFile = $currentFileInfos['filename'] . '.' . $currentFileInfos['extension'];
    printf("usage:  {$execFile} code_language translation_file.po translated_file.ser\n"
        . "  code_language          code of the language (ex: fr, es, de, ...)\n"
        . "  translation_file.po    file where the translation exists\n"
        . "  translated_file.ser    Serialized file where the translation will be converted\n");
}

define('TOKEN_LENGTH', 6);

if ($argc === 3) {
    $translationFileInfos = pathinfo($argv[1]);
    $destinationFile = $translationFileInfos['dirname'] . '/'
        . $translationFileInfos['filename'] . '.json';
    createTranslationFile($languageCode, $argv[2], $destinationFile);
}

if ($argc === 4) {
    if (file_exists($argv[3])) {
        if (false === unlink($argv[3])) {
            exit("Destination file already exists, impossible to delete it\n");
        }
    }
    $destinationFileInfos = pathinfo($argv[3]);
    $destinationDirectory = $destinationFileInfos['dirname'];
    if (!is_dir($destinationDirectory)) {
        if (false === mkdir($destinationDirectory, 0775, true)) {
            exit(
                sprintf("Impossible to create directory '%s'\n", $destinationDirectory)
            );
        }
    }
    createTranslationFile($languageCode, $argv[2], $argv[3]);
}

/**
 * Create translation file for React
 *
 * @param string $languageCode Code of the language (ex: fr, es, de, ...)
 * @param string $translationFile  File where the translation exists
 * @param string $destinationFile  Serialized file where the translation will be converted
 */
function createTranslationFile(
    string $languageCode,
    string $translationFile,
    string $destinationFile
): void {
    $translations = [];
    $englishTranslation = [];
    $isDefaultTranslation = $languageCode === 'en';

    $id = null;
    $translation = null;

    if ($fleHandler = fopen($translationFile, 'r')) {
        while (false !== ($line = fgets($fleHandler))) {
            $line = trim($line);

            // Removes double-quotes character that surround the text
            if (preg_match('/^(?:(msgid|msgstr)\s+)?"(.*)"\s*$/', $line, $matches)) {
                if ($matches[1] === 'msgid') {
                    $id = $matches[2];
                    $translation = null;
                } elseif ($matches[1] === 'msgstr') {
                    $translation = $matches[2];
                } elseif ($id !== null && $translation === null) {
                    $id .= $matches[2];
                } elseif ($id !== null && $translation !== null) {
                    $translation .= $matches[2];
                }
            } elseif (!empty($id) && !empty($translation)) {
                $englishTranslation[$id] = $id;
                if (!$isDefaultTranslation) {
                    // Only if the code of language is not 'en'
                    $translations[$id] = $translation;
                }
                $id = null;
                $translation = null;
            }
        }

        fclose($fleHandler);
    }

    if (!empty($id) && !empty($translation)) {
        $englishTranslation[$id] = $id;
        if (!$isDefaultTranslation) {
            $translations[$id] = $translation;
        }
    }

    $final['en'] = $englishTranslation;
    if (!$isDefaultTranslation) {
        // Only if the code of language is not 'en'
        $final[$languageCode] = $translations;
    }
    if (0 === file_put_contents($destinationFile, serialize($final))) {
        exit(
            sprintf("Impossible to create destination file '%s'\n", $destinationFile)
        );
    }
    chmod($destinationFile, 0664);
}
