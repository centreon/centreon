<?php

require("./functions.php");

$my_audit_file=$_GET["audit_file"];

download_audit($my_audit_file);

die;
?>