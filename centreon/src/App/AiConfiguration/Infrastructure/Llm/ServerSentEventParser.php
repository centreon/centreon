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

namespace App\AiConfiguration\Infrastructure\Llm;

/**
 * Minimal incremental Server-Sent Events parser: feed it raw HTTP chunks,
 * it returns the "data" payload of each complete event.
 */
final class ServerSentEventParser
{
    private string $buffer = '';

    /**
     * @return list<string>
     */
    public function push(string $chunk): array
    {
        $this->buffer = str_replace("\r\n", "\n", $this->buffer . $chunk);

        $payloads = [];
        while (($position = mb_strpos($this->buffer, "\n\n")) !== false) {
            $rawEvent = mb_substr($this->buffer, 0, $position);
            $this->buffer = mb_substr($this->buffer, $position + 2);

            $dataLines = [];
            foreach (explode("\n", $rawEvent) as $line) {
                if (str_starts_with($line, 'data:')) {
                    $dataLines[] = ltrim(mb_substr($line, 5));
                }
            }

            if ($dataLines !== []) {
                $payloads[] = implode("\n", $dataLines);
            }
        }

        return $payloads;
    }
}
