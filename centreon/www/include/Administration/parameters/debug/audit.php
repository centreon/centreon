<?php

require("./functions.php");

$end_screen = "end_screen.html";

// files to archive
$conf_and_log_files_to_archive = [
        "/etc/centreon*",
        "/etc/cron.d",
        "/var/log/centreon*/*.log",
        "/var/log/messages",
        "/var/log/syslog",
        "/var/log/php-fpm/*.log",
        "/var/log/httpd/access_log",
        "/var/log/httpd/error_log",
        "/var/log/apache2/access.log",
        "/var/log/apache2/error.log",
        "/var/log/apache2/other_vhosts_access.log"
  ];

// Comment the line below if it takes to long to generate
 $conf_and_log_files_to_archive [] = generateAudit();

$archive=generateArchive($conf_and_log_files_to_archive);

include('ending_screen.html');
?>

<script>
        window.location.replace('download.php?audit_file=<?=$archive?>');