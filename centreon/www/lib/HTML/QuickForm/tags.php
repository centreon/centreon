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
 * Description of tags
 *
 * @author Toufik MECHOUET
 */
class HTML_QuickForm_tags extends HTML_QuickForm_select2
{
    /**
     * @param string $elementName
     * @param string $elementLabel
     * @param array $options
     * @param array $attributes
     * @param string $sort
     */
    public function __construct(
        $elementName = null,
        $elementLabel = null,
        $options = null,
        $attributes = null,
        $sort = null
    ) {
        global $centreon;

        $this->_ajaxSource = false;
        $this->_defaultSelectedOptions = '';
        $this->_multipleHtml = '';
        $this->_allowClear = true;
        $this->_elementHtmlName = $this->getName();
        $this->_defaultDataset = [];
        $this->_defaultDatasetOptions = [];
        $this->_jsCallback = '';
        $this->_allowClear = false;
        $this->_pagination = $centreon->optGen['selectPaginationSize'];
        $this->parseCustomAttributes($attributes);

        parent::__construct($elementName, $elementLabel, $options, $attributes);
    }

    /**
     * @return string
     */
    public function getJsInit()
    {
        $allowClear = 'true';
        if (false === $this->_allowClear || $this->_flagFrozen) {
            $allowClear = 'false';
        }

        $disabled = 'false';
        if ($this->_flagFrozen) {
            $disabled = 'true';
        }

        $ajaxOption = '';
        $defaultData = '';
        if ($this->_ajaxSource) {
            $ajaxOption = 'ajax: {
                url: "' . $this->_availableDatasetRoute . '"
            },';

            if ($this->_defaultDatasetRoute && (count($this->_defaultDataset) == 0)) {
                $additionnalJs = $this->setDefaultAjaxDatas();
            } else {
                $this->setDefaultFixedDatas();
            }
        } else {
            $defaultData = $this->setFixedDatas() . ',';
        }

        $additionnalJs = ' jQuery(".select2-selection").each(function(){'
            . ' if(typeof this.isResiable == "undefined" || this.isResiable){'
            . ' jQuery(this).resizable({ maxWidth: 500, '
            . ' minWidth : jQuery(this).width() != 0 ? jQuery(this).width() : 200, '
            . ' minHeight : jQuery(this).height() != 0 ? jQuery(this).height() : 45 });'
            . ' this.isResiable = true; '
            . ' }'
            . ' }); ';

        return '<script>
            jQuery(function () {
                var $currentSelect2Object' . $this->getName() . ' = jQuery("#' . $this->getName() . '").centreonSelect2({
                    allowClear: ' . $allowClear . ',
                    pageLimit: ' . $this->_pagination . ',
                    select2: {
                        tags: true,
                        ' . $ajaxOption . '
                        ' . $defaultData . '
                        placeholder: "' . $this->getLabel() . '",
                        disabled: ' . $disabled . '
                    }
                });

                ' . $additionnalJs . '
            });
         </script>';
    }
}

if (class_exists('HTML_QuickForm')) {
    (new HTML_QuickForm())->registerElementType(
        'tags',
        'HTML/QuickForm/tags.php',
        'HTML_QuickForm_tags'
    );
}
