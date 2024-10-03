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
     * @return HtmlSanitizer
     */
    public static function create(): HtmlSanitizer
    {
        return new HtmlSanitizer();
    }

    /**
     * @param string $string
     *
     * @return string
     */
    public function sanitize(string $string): string {
        return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @param string $string
     * @param array|null $allowedTags
     *
     * @return string
     */
    public function removeTags(string $string, array $allowedTags = null): string
    {
        return strip_tags($string, $allowedTags);
    }

}