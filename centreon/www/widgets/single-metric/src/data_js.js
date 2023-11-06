/**
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
 
 var timeout;

jQuery(function() {
    loadMetric();
});

function loadMetric() {
    jQuery.ajax({
        url: './index.php',
        type: 'GET',
        data: {
            widgetId: widgetId
        },
        success : function(htmlData) {
            let data = jQuery(htmlData).filter('#metric');  
            let $container = $('#metric');
            let h;

            $container.html(data);

            h = $container.scrollHeight + 10;

            if(h){
                parent.iResize(window.name, h);
            } else {
                parent.iResize(window.name, 200);
            }
        }
    });

    if (autoRefresh && autoRefresh != "") {
        if (timeout) {
            clearTimeout(timeout);
        }

        timeout = setTimeout(loadMetric, (autoRefresh * 1000));
    }
}
