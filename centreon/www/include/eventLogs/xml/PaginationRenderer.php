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

declare(strict_types=1);

include_once _CENTREON_PATH_ . 'www/class/centreonXML.class.php';

class PaginationRenderer
{
    public function __construct(private CentreonXML $buffer)
    {
    }

    /**
     * Renders navigation pages as xml nodes.
     *
     * @param Paginator $paginator
     */
    public function render(Paginator $paginator): void
    {
        if ($paginator->totalPagesCount <= 1) {
            return;
        }

        $this->addNavigation('prev', $paginator->getPageNumberPrevious());

        foreach ($paginator as $page) {
            $this->addPage($paginator, $page);
        }

        $this->addNavigation('next', $paginator->getPageNumberNext());
    }

    /**
     * Adds next or previous page into the xml as a new node.
     *
     * @param string $elName
     * @param ?int $pageNb
     */
    private function addNavigation(string $elName, ?int $pageNb): void
    {
        $this->buffer->startElement($elName);
        if (is_int($pageNb)) {
            $this->buffer->writeAttribute('show', 'true');
            $this->buffer->text((string) $pageNb);
        } else {
            $this->buffer->writeAttribute('show', 'false');
            $this->buffer->text('none');
        }
        $this->buffer->endElement();
    }

    /**
     * Adds navigation page into the xml as a new node.
     *
     * @param Paginator $paginator
     * @param int $pageNb
     */
    private function addPage(Paginator $paginator, int $pageNb): void
    {
        $active = $paginator->isActive($pageNb);
        $url = $paginator->getUrl($pageNb);

        $this->buffer->startElement('page');
        $this->buffer->writeElement('selected', $active ? '1' : '0');
        $this->buffer->writeElement('num', (string) $pageNb);
        $this->buffer->writeElement('url_page', $url);
        $this->buffer->writeElement('label_page', (string) $pageNb);
        $this->buffer->endElement();
    }
}