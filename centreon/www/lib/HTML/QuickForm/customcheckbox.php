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

/**
 * HTML class for a checkbox type field
 *
 * PHP versions 4 and 5
 *
 * LICENSE: This source file is subject to version 3.01 of the PHP license
 * that is available through the world-wide-web at the following URI:
 * http://www.php.net/license/3_01.txt If you did not receive a copy of
 * the PHP License and are unable to obtain it through the web, please
 * send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category    HTML
 * @package     HTML_QuickForm
 * @author      Adam Daniel <adaniel1@eesus.jnj.com>
 * @author      Bertrand Mansion <bmansion@mamasam.com>
 * @author      Alexey Borzov <avb@php.net>
 * @copyright   2001-2011 The PHP Group
 * @license     http://www.php.net/license/3_01.txt PHP License 3.01
 * @version     CVS: $Id$
 * @see        http://pear.php.net/package/HTML_QuickForm
 */

/**
 * HTML class for a checkbox type field
 *
 * @category    HTML
 * @package     HTML_QuickForm
 * @author      Adam Daniel <adaniel1@eesus.jnj.com>
 * @author      Bertrand Mansion <bmansion@mamasam.com>
 * @author      Alexey Borzov <avb@php.net>
 * @version     Release: 3.2.14
 * @since       1.0
 */
class HTML_QuickForm_customcheckbox extends HTML_QuickForm_checkbox
{
    public $checkboxTemplate;

    /**
     * @return string
     */
    public function toHtml()
    {
        $oldHtml = parent::toHtml();
        $matches = ['{element}', '{id}'];
        $replacements = [$oldHtml, $this->getAttribute('id')];

        return str_replace($matches, $replacements, $this->checkboxTemplate);
    }

    public function setCheckboxTemplate($checkboxTemplate): void
    {
        $this->checkboxTemplate = $checkboxTemplate;
    }
}

if (class_exists('HTML_QuickForm')) {
    (new HTML_QuickForm())->registerElementType(
        'customcheckbox',
        'HTML/QuickForm/customcheckbox.php',
        'HTML_QuickForm_customcheckbox'
    );
}
