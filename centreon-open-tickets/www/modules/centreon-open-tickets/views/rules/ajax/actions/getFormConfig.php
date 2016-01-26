<?php

$resultat = array(
    "code" => 0,
    "msg" => 'ok'
);

// Load provider class
if (is_null($get_information['provider_id'])) {
    $resultat['code'] = 1;
    $resultat['msg'] = 'Please set provider_id';
    return ;
}

$provider_name = null;
foreach ($register_providers as $name => $id) {
    if ($id == $get_information['provider_id']) {
        $provider_name = $name;
        break;
    }
}

if (is_null($provider_name) || !file_exists($centreon_open_tickets_path . 'providers/' . $provider_name . '/' . $provider_name . 'Provider.class.php')) {
    $resultat['code'] = 1;
    $resultat['msg'] = 'Please set a provider';
    return ;
}

require_once $centreon_open_tickets_path . 'providers/' . $provider_name . '/' . $provider_name . 'Provider.class.php';

$classname = $provider_name . 'Provider';
$centreon_provider = new $classname($rule, $centreon_path, $centreon_open_tickets_path, $get_information['rule_id']);

try {
    $resultat['result'] = $centreon_provider->getConfig();
} catch (Exception $e) {
    $resultat['code'] = 1;
    $resultat['msg'] = $e->getMessage();
}

?>