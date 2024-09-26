<?php

require_once __DIR__ . "/webService.class.php";
require_once _CENTREON_PATH_ . "/www/class/centreonRestHttp.class.php";

/**
 * Class
 *
 * @class CentreonProxy
 */
class CentreonProxy extends CentreonWebService
{
    /**
     * @return array
     */
    public function postCheckConfiguration()
    {
        $proxyAddress = $this->arguments['url'];
        $proxyPort = $this->arguments['port'];
        try {
            $testUrl = 'https://api.imp.centreon.com/api/pluginpack/pluginpack';
            $restHttpLib = new CentreonRestHttp();
            $restHttpLib->setProxy($proxyAddress, $proxyPort);
            $restHttpLib->call($testUrl);
            $outcome = true;
            $message = _('Connection Successful');
        } catch (Exception $e) {
            $outcome = false;
            $message = _('Could not establish connection to Centreon IMP servers (') . $e->getMessage() . ')';
        }

        return ['outcome' => $outcome, 'message' => $message];
    }

    /**
     * Authorize to access to the action
     *
     * @param string $action The action name
     * @param array $user The current user
     * @param bool $isInternal If the api is call in internal
     * @return bool If the user has access to the action
     */
    public function authorize($action, $user, $isInternal = false)
    {
        return $isInternal;
    }
}
