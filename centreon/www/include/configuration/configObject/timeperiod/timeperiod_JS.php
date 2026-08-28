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

?>
<script type="text/javascript" src="./include/common/javascript/jquery/plugins/qtip/jquery-qtip.js"></script>
<script type="text/javascript" src="./lib/HTML/QuickForm/qfamsHandler-min.js"></script>
<script type="text/javascript" src="./include/common/javascript/jquery/plugins/centreon/jquery.centreonValidate.js"></script>
<script type="text/javascript">

    /*
     *  Add a blank exception row
     */
    function addBlankInput() {
        var container = document.getElementById('exceptionTable');
        var row = document.createElement('div');
        row.className = 'cf-dynamic-entry';
        row.id = 'trExceptionInput_' + globalj;

        var keyElem = document.createElement('input');
        keyElem.id = 'exceptionInput_' + globalj;
        keyElem.name = 'exceptionInput_' + globalj;
        keyElem.value = '';
        keyElem.placeholder = <?php echo json_encode(_('Day range'), JSON_THROW_ON_ERROR); ?>;
        keyElem.className = 'v_required v_regex';
        keyElem.setAttribute('data-validator', '^((([0-9]{4}-[0-9]{2}-[0-9]{2})|(day ([0-9]{1,2}|-[0-9]{1,2})( - ([0-9]{1,2}|-[0-9]{1,2}))?)|((sunday|monday|tuesday|wednesday|thursday|friday|saturday) ([0-9]{1,2}|-[0-9]{1,2})( (january|february|march|april|may|june|july|august|september|october|november|december))?)|((january|february|march|april|may|june|july|august|september|october|november|december) ([0-9]{1,2}|-[0-9]{1,2})( - ([0-9]{1,2}|-[0-9]{1,2}))?))( - )?( \/ [0-9]{1,2})?)+$');

        var valueElem = document.createElement('input');
        valueElem.id = 'exceptionTimerange_' + globalj;
        valueElem.name = 'exceptionTimerange_' + globalj;
        valueElem.value = '';
        valueElem.placeholder = <?php echo json_encode(_('Time range (HH:MM-HH:MM)'), JSON_THROW_ON_ERROR); ?>;
        valueElem.className = 'v_required v_regex';
        valueElem.setAttribute('data-validator', '^([0-9]{2}:[0-9]{2}-[0-9]{2}:[0-9]{2}(,)?)+$');

        var deleteBtn = document.createElement('button');
        deleteBtn.type = 'button';
        deleteBtn.className = 'cf-dynamic-delete';
        deleteBtn.innerHTML = '&#128465;';
        deleteBtn.title = <?php echo json_encode(_('Delete'), JSON_THROW_ON_ERROR); ?>;
        var btnId = globalj;
        deleteBtn.onclick = function () {
            if (window.confirm(<?php echo json_encode(_('Do you confirm this deletion?'), JSON_THROW_ON_ERROR); ?>)) {
                document.getElementById('trExceptionInput_' + btnId).remove();
            }
        };

        row.appendChild(keyElem);
        row.appendChild(valueElem);
        row.appendChild(deleteBtn);
        container.appendChild(row);

        globalj++;
        document.getElementById('hiddenExInput').value = globalj;
    }

    /*
     * Display existing exceptions on page load
     */
    function displayExistingExceptions(max) {
        var _o = <?php echo json_encode((string) $o, JSON_THROW_ON_ERROR); ?>;

        for (var i = 0; i < max; i++) {
            var container = document.getElementById('exceptionTable');
            var row = document.createElement('div');
            row.className = 'cf-dynamic-entry';
            row.id = 'trExceptionInput_' + globalj;

            var keyElem = document.createElement('input');
            keyElem.id = 'exceptionInput_' + globalj;
            keyElem.name = 'exceptionInput_' + globalj;
            keyElem.value = globalExceptionTabName[globalj];
            keyElem.placeholder = <?php echo json_encode(_('Day range'), JSON_THROW_ON_ERROR); ?>;

            var valueElem = document.createElement('input');
            valueElem.id = 'exceptionTimerange_' + globalj;
            valueElem.name = 'exceptionTimerange_' + globalj;
            valueElem.value = globalExceptionTabTimerange[globalj];
            valueElem.placeholder = <?php echo json_encode(_('Time range (HH:MM-HH:MM)'), JSON_THROW_ON_ERROR); ?>;

            if (_o == "w") {
                keyElem.disabled = true;
                valueElem.disabled = true;
            }

            row.appendChild(keyElem);
            row.appendChild(valueElem);

            if (_o != "w") {
                var deleteBtn = document.createElement('button');
                deleteBtn.type = 'button';
                deleteBtn.className = 'cf-dynamic-delete';
                deleteBtn.innerHTML = '&#128465;';
                deleteBtn.title = <?php echo json_encode(_('Delete'), JSON_THROW_ON_ERROR); ?>;
                deleteBtn.onclick = function () {
                    if (window.confirm(<?php echo json_encode(_('Do you confirm this deletion?'), JSON_THROW_ON_ERROR); ?>)) {
                        document.getElementById('trExceptionInput_' + this.dataset.rowId).remove();
                    }
                };
                deleteBtn.dataset.rowId = globalj;
                row.appendChild(deleteBtn);
            }

            container.appendChild(row);
            globalj++;
        }
        document.getElementById('hiddenExInput').value = globalj;
    }

    function purgeHideInput() {
        jQuery('.cf-tab-content').each(function(idx, el) {
            if (jQuery(el).css('display') === 'none') {
                jQuery(el).find(':input').each(function(idx, input) {
                    jQuery(input).qtip('destroy');
                });
            }
        });
    }

    function formValidate() {
        jQuery('#Form').centreonValidate();
        jQuery('#Form').centreonValidate('validate');

        if (jQuery('#Form').centreonValidate('hasError')) {
            purgeHideInput();
            return false;
        }

        return true;
    }

    /*
     * Global variables
     */
    var globalj = 0;
    var globalExceptionTabId = new Array();
    var globalExceptionTabName = new Array();
    var globalExceptionTabTimerange = new Array();
    var globalExceptionTabTimeperiodId = new Array();

</script>
