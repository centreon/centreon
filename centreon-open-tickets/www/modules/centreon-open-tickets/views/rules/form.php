<?php
/*
 * Copyright 2016-2019 Centreon (http://www.centreon.com/)
 *
 * Centreon is a full-fledged industry-strength solution that meets
 * the needs in IT infrastructure and application monitoring for
 * service performance.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,*
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

$path = "./modules/centreon-open-tickets/views/rules/";
$tpl = new Smarty();
$tpl = initSmartyTpl($path, $tpl);

$required_field = '&nbsp;<font color="red" size="1">*</font>';

$tpl->assign('host', array('label' => _("Hosts")));
$tpl->assign('rule', array('label' => _("Rules")));

$tpl->assign("img_wrench", "./modules/centreon-open-tickets/images/wrench.png");
$tpl->assign("img_info", "./modules/centreon-open-tickets/images/information.png");

$tpl->assign("sort1", _("General"));
$tpl->assign("sort2", _("Advanced"));

$tpl->assign("header", array("title" => _("Rules"), "general" => _("General information")));

$result_rule = $rule->getAliasAndProviderId($ruleId);

$tpl->assign("page", $p);
$tpl->assign('rule_id', $ruleId);

$rule_alias_html = '<input size="30" name="rule_alias" type="text" value="' .
    (isset($result_rule['alias']) ? $result_rule['alias'] : '') . '" />';
$provider_html = '<select id="provider_id" name="provider_id"><option value=""></option>';
ksort($register_providers);
foreach ($register_providers as $name => $value) {
    $selected = '';
    if (isset($result_rule['provider_id']) && $result_rule['provider_id'] == $value) {
        $selected = ' selected ';
    }
    $provider_html .= '<option value="' . $value . '"' . $selected . '>' . $name . '</option>';
}
$provider_html .= '</select>';

$array_rule_form = array(
    'rule_alias' => array('label' => _("Rule name") . $required_field, 'html' => $rule_alias_html),
    'rule_provider' => array('label' => _("Provider") . $required_field, 'html' => $provider_html)
);

$tpl->assign('form', $array_rule_form);

$tpl->display("form.ihtml");
