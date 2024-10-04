<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

/**
 * Class
 *
 * @class HtmlSanitizer
 */
final class HtmlSanitizer {

    /**
     * HtmlSanitizer constructor
     *
     * @param string $string
     */
    private function __construct(private string $string) {}

    /**
     * @param string $string
     *
     * @return HtmlSanitizer
     */
    public static function createFromString(string $string): HtmlSanitizer
    {
        return new HtmlSanitizer($string);
    }

    /**
     * @return HtmlSanitizer
     */
    public function sanitize(): HtmlSanitizer {
        $this->string = htmlspecialchars($this->string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return $this;
    }

    /**
     * @param array|null $allowedTags
     *
     * @return HtmlSanitizer
     */
    public function removeTags(array $allowedTags = null): HtmlSanitizer
    {
        $this->string = strip_tags($this->string, $allowedTags);
        return $this;
    }

    /**
     * @return string
     */
    public function getString(): string
    {
        return $this->string;
    }

}