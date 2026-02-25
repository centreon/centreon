<?php
/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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
<script type="text/javascript">

    var listArea;
    var macroSvc = new Array();
    var macroHost = new Array();

    function goPopup() {
        var cmd_line;
        var tmpStr;
        var reg = new RegExp("(\n)", "g");

        listArea = document.getElementById('listOfArg');
        tmpStr = listArea.value;
        tmpStr = encodeURIComponent(tmpStr.replace(reg, ";;;"));
        cmd_line = document.getElementById('command_line').value;

        var popin = jQuery('<div id="config-popin">');
        popin.centreonPopin({
            url: './include/configuration/configObject/command/formArguments.php?cmd_line=' + cmd_line +
            '&textArea=' + tmpStr,
            open: true,
            ajaxDataType: 'html'
        });
    }

    function setDescriptions() {
        var i;
        var tmpStr2;
        var listDiv;

        tmpStr2 = "";
        listArea = document.getElementById('listOfArg');
        listDiv = document.getElementById('listOfArgDiv');
        for (i = 1; document.getElementById('desc_' + i); i++) {
            tmpStr2 += "ARG" + document.getElementById('macro_' + i).value + " : " +
                document.getElementById('desc_' + i).value + "\n";
        }
        listArea.cols = 100;
        listArea.rows = i;
        listArea.value = tmpStr2;
        listDiv.style.visibility = "visible";
        jQuery('#config-popin').centreonPopin('close');
    }

    function closeBox() {
        jQuery('#config-popin').centreonPopin('close');
    }

    function clearArgs() {
        listArea = document.getElementById('listOfArg');
        listArea.value = "";
    }

    function manageMacros() {
        var commandLine = document.Form.command_line.value;
        var commandId = document.Form.command_id.value;
        var tmpStr = "";

        var popin = jQuery('<div id="config-popin">');
        popin.centreonPopin({
            url: './include/configuration/configObject/command/formMacros.php?cmd_line=' + commandLine +
            '&cmdId=' + commandId + '&textArea=' + tmpStr,
            open: true,
            ajaxDataType: 'html'
        });
    }

    function setMacrosDescriptions() {
        var i;
        var tmpStr2;
        var listDiv;

        tmpStr2 = "";
        listArea = document.getElementById('listOfMacros');
        listDiv = document.getElementById('listOfMacros');
        for (i = 0; document.getElementById('desc_' + i); i++) {
            var type = "HOST";
            if (document.getElementById('type_' + i).value == 2) {
                type = "SERVICE";
            }
            tmpStr2 += "MACRO (" + type + ") " + document.getElementById('macro_' + i).value + " : " +
                document.getElementById('desc_' + i).value + "\n";
        }

        listArea.cols = 100;
        listArea.rows = i;

        listArea.value = tmpStr2;
        listDiv.style.visibility = "visible";
        jQuery('#config-popin').centreonPopin('close');
    }

    function checkType(value) {
        var action = jQuery('form#Form').attr('action');
        switch (value) {
            case '1':
                action = action.replace(/p=\d+/, 'p=60802');
                break;
            case '2':
                action = action.replace(/p=\d+/, 'p=60801');
                break;
            case '3':
                action = action.replace(/p=\d+/, 'p=60803');
                break;
            case '4':
                action = action.replace(/p=\d+/, 'p=60807');
                break;
            default:
                action = action.replace(/p=\d+/, 'p=60801');
                break;
        }

        if (action.match(/&type=/)) {
            action = action.replace(/&type=\d+/, '&type=' + value);
        } else {
            action += '&type=' + value;
        }

        jQuery('form#Form').attr('action', action);
    }

</script>
