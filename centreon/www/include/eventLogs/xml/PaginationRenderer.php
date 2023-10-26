<?php
/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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

include_once _CENTREON_PATH_ . 'www/class/centreonXML.class.php';

class PaginationRenderer
{
    public function __construct(private CentreonXML $buffer)
    {
    }

    /**
     * Renders navigation pages as xml nodes
     *
     * @param <string|int, <string|int|bool>> $pages
     * @return void
     */
    public function render(array $pages): void
    {
        if (count($pages) <= 1) {
            return;
        }

        $previousBtnText = array_key_exists('previous', $pages) ? $pages['previous']['num'] : null;
        $this->addNavigation('prev', (string) $previousBtnText);

        foreach ($pages as $key => $page) {
            if (is_numeric($key)) {
                $this->addPage($page);
            }
        }

        $nextBtnText = array_key_exists('next', $pages) ? $pages['next']['num'] : null;
        $this->addNavigation('next', (string) $nextBtnText);
    }

    /**
     * Ads next or previous page into the xml as a new node
     *
     * @param string $elName
     * @param string|null $text
     * @return void
     */
    private function addNavigation(string $elName, ?string $text): void
    {
        $this->buffer->startElement($elName);
        if (is_string($text)) {
            $this->buffer->writeAttribute('show', 'true');
            $this->buffer->text($text);
        } else {
            $this->buffer->writeAttribute('show', 'false');
            $this->buffer->text('none');
        }
        $this->buffer->endElement();
    }

    /**
     * Ads navigation page into the xml as a new node
     *
     * @param array $page
     * @return void
     */
    private function addPage(array $page)
    {
        $this->buffer->startElement('page');
        $this->buffer->writeElement('selected', $page['active'] ? '1' : '0');
        $this->buffer->writeElement('num', $page['num']);
        $this->buffer->writeElement('url_page', $page['url_page']);
        $this->buffer->writeElement('label_page', $page['label_page']);
        $this->buffer->endElement();
    }
}