<?php
/**
 * Created by PhpStorm.
 * User: Guillaume
 * Date: 11/08/2016
 * Time: 16:42
 */

if (file_exists($centreon_path . "/www/include/common/webServices/rest/webService.class.php")) {
    // Centreon < 2.8
    require_once $centreon_path . "/www/include/common/webServices/rest/webService.class.php";
} else {
    // Centreon >= 2.8
    require_once $centreon_path . "/www/api/class/webService.class.php";
}

class CentreonWidgetHostMonitoring extends CentreonWebService
{
    protected $arguments= array();

    /**
     *
     * @var type
     */
    protected $pearDBMonitoring;
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::construct();
    }

    public function getDefaultValues()
    {
        //Check for select2 argument
        if (!isset($this->arguments['q'])) {
            throw new Exception('Missing parameter : Widget ID');
        } else {
            $q = $this->arguments['q'];
        }

        // Faire requête pour trouver id des hosts avec 'q'
        // faire un pull de centreon pour reprendre le code de kevin

        $ids = "SELECT SQL_CALC_FOUND_ROWS DISTINCT host_id
               FROM widgets
               WHERE widget_id IN ($q)";

        $query = "SELECT SQL_CALC_FOUND_ROWS DISTINCT host_id, host_name"
                 . "FROM host"
                 . "WHERE host_id IN ($ids)";

        $DBresult = $this->pearDBMonitoring->query($query);

        $total = $this->pearDBMonitoring->numberRows();

        $results = array ();
        while ($row = $DBresult->fetchRow()) {
            $results[] = array(
                'id' => $row['host_id'],
                'text' => $row['host_name']
            );
        }

        return array(
            'items' => $results,
            'total' => $total
        );
    }
}