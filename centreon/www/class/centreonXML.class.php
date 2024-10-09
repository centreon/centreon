<?php
/*
 * Copyright 2005-2015 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

/**
 * Class
 *
 * @class CentreonXML
 * @description Class that is used for writing XML in utf_8 only!
 */
class CentreonXML
{
    /** @var XMLWriter */
    public $buffer;

    /**
     * CentreonXML constructor
     *
     * @param bool $indent
     */
    public function __construct($indent = false)
    {
        $this->buffer = new XMLWriter();
        $this->buffer->openMemory();
        if ($indent) {
            $this->buffer->setIndent($indent);
        }
        $this->buffer->startDocument('1.0', 'UTF-8');
    }

    /**
     * Clean string
     *
     * @param string $str
     *
     * @return string
     */
    protected function cleanStr($str)
    {
        $str = preg_replace('/[\x00-\x09\x0B-\x0C\x0E-\x1F\x0D]/', "", $str);
        return $str;
    }

    /**
     * Starts an element that contains other elements
     *
     * @param string $element_tag
     *
     * @return void
     */
    public function startElement($element_tag): void
    {
        $this->buffer->startElement($element_tag);
    }

    /**
     * Ends an element (closes tag)
     *
     * @return void
     */
    public function endElement(): void
    {
        $this->buffer->endElement();
    }

    /**
     * Simply puts text
     *
     * @param string $txt
     * @param bool $cdata
     * @param int $encode
     *
     * @return void
     */
    public function text($txt, $cdata = true, $encode = 0): void
    {
        $txt = $this->cleanStr($txt);
        $txt = html_entity_decode($txt);
        if ($encode || !$this->is_utf8($txt)) {
            $this->buffer->writeCData(mb_convert_encoding($txt, 'UTF-8', 'ISO-8859-1'));
        } elseif ($cdata) {
            $this->buffer->writeCData($txt);
        } else {
            $this->buffer->text($txt);
        }
    }

    /**
     * Checks if string is encoded
     *
     * @param string $string
     *
     * @return int
     */
    protected function is_utf8($string)
    {
        if (mb_detect_encoding($string, "UTF-8", true) == "UTF-8") {
            return 1;
        }
        return 0;
    }

    /**
     * Creates a tag and writes data
     *
     * @param string $element_tag
     * @param string $element_value
     * @param int $encode
     *
     * @return void
     */
    public function writeElement($element_tag, $element_value, $encode = 0): void
    {
        $this->startElement($element_tag);
        $element_value = $this->cleanStr($element_value);
        $element_value = html_entity_decode($element_value);
        if ($encode || !$this->is_utf8($element_value)) {
            $this->buffer->writeCData(mb_convert_encoding($element_value, 'UTF-8', 'ISO-8859-1'));
        } else {
            $this->buffer->writeCData($element_value);
        }

        $this->endElement();
    }

    /**
     * Writes attribute
     *
     * @param string $att_name
     * @param string $att_value
     * @param bool $encode
     *
     * @return void
     */
    public function writeAttribute($att_name, $att_value, $encode = false): void
    {
        $att_value = $this->cleanStr($att_value);
        if ($encode) {
            $this->buffer->writeAttribute($att_name, mb_convert_encoding(html_entity_decode($att_value), 'UTF-8', 'ISO-8859-1'));
        } else {
            $this->buffer->writeAttribute($att_name, html_entity_decode($att_value));
        }
    }

    /**
     * Output the whole XML buffer
     *
     * @return void
     */
    public function output(): void
    {
        $this->buffer->endDocument();
        print $this->buffer->outputMemory(true);
    }

    /**
     * @param string|null $filename
     *
     * @return void
     * @throws RuntimeException
     */
    public function outputFile($filename = null): void
    {
        $this->buffer->endDocument();
        $content = $this->buffer->outputMemory(true);
        if ($handle = fopen($filename, 'w')) {
            if (strcmp($content, "") && !fwrite($handle, $content)) {
                throw new RuntimeException('Cannot write to file "' . $filename . '"');
            }
        } else {
            print "Can't open file: $filename";
        }
    }
}
