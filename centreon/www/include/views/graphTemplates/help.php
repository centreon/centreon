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

$help = [];

/**
 * General Information
 */
$help['tip_template_name'] = dgettext('help', 'Name of graph template.');
$help['tip_vertical_label'] = dgettext('help', 'Vertical Label (Y-axis).');
$help['tip_width'] = dgettext('help', 'Width of grid. Used to export the chart.');
$help['tip_height'] = dgettext('help', 'Height of grid. Used to export the chart.');
$help['tip_lower_limit'] = dgettext('help', 'Lower limit of grid.');
$help['tip_upper_limit'] = dgettext('help', 'Upper limit of grid.');
$help['tip_base'] = dgettext('help', 'Base value.');

/**
 * Legend
 */
$help['tip_scale_graph_values'] = dgettext('help', 'Enables auto scale of graph.');
$help['tip_default_centreon_graph_template'] = dgettext('help', 'Set as default graph template.');
$help['tip_comments'] = dgettext('help', 'Comments regarding the graph template.');
