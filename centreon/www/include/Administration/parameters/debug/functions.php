<?php

function generateArchive($files) {

        $epoch                    = time();
        $archive_destination_path = "/tmp/";
        $archive_name             = "conf_and_logs_$epoch.tar.gz";
        $full_archive_path        = $archive_destination_path.$archive_name;
        $files_to_archive         = "";

        foreach ($files as $file){
                $files_to_archive .= " $file";
        }

        $zipping_error = shell_exec("sudo /bin/tar -czvf $full_archive_path $files_to_archive 2>&1");

        if (file_exists($full_archive_path)) {
                audit_log("Zipped archive generated successfully $full_archive_path");
        }
        else {
                audit_log("[ERROR] Zipped archive generation failed using apache user ! Checkout why below :");
                                audit_log("[DEBUG] sudo /bin/tar -czvf $full_archive_path $files_to_archive : $zipping_error");
        }
    return $full_archive_path;
}

function generateAudit() {

        $audit_scrpit_path   = getAuditScript();
        $audit_result_folder = "/tmp";
        $epoch               = time();
        $audit_result_path   = "$audit_result_folder/$epoch-gorgoneaudit.md";
        $audit_options       = "--markdown=$audit_result_path";
        $timeout_cmd         = "/bin/timeout";
        $audit_timeout       = "60"; // if you have a lot of poller you may ajust this value.
        $audit_command       = "$timeout_cmd $audit_timeout $audit_scrpit_path $audit_options";

        audit_log("[INFO] Generating audit ...");
        $output = shell_exec($audit_command);
        audit_log("[INFO] Audit output : $output");

        if (file_exists($audit_result_path)) {
                audit_log("[INFO] Audit file generated successfully $audit_result_path");
        }
        else {
                audit_log("[WARNING] Audit file generation failed ! Checkout the item n°5 of the following documentation for help : https://github.com/y-kacher/support_debug_archive?tab=readme-ov-file#known-issues-and-solutions ");
        }

        return $audit_result_path;
}

function getAuditScript() {

        $url = "https://raw.githubusercontent.com/centreon/centreon-gorgone/develop/contrib/gorgone_audit.pl";
        $directory_audit_script = "/usr/share/centreon/www/include/Administration/parameters/debug/";
        $audit_script_name = "gorgone_audit.pl";
        $audit_scrpit_path = $directory_audit_script.$audit_script_name;

        if (!file_exists($audit_scrpit_path)) {
                audit_log("[INFO] Audit Script not found !");
                audit_log("[INFO] Downloading audit script from $url");

                // Get content from url without setting allow_url_fopen=1
                $curlSession = curl_init();
                curl_setopt($curlSession, CURLOPT_URL, $url);
                curl_setopt($curlSession, CURLOPT_BINARYTRANSFER, true);
                curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, true);

                $audit_script = curl_exec($curlSession);
                curl_close($curlSession);

                if (file_put_contents($audit_scrpit_path, $audit_script)){
                        audit_log("[INFO] Audit script downloaded successfully");
                        audit_log("[INFO] Audit script path is : $audit_scrpit_path");
  
                        shell_exec("chmod +x $audit_scrpit_path");
                        audit_log("[INFO] Execution right added to $audit_scrpit_path");
                }
                else {
                        audit_log("[WARNING] Audit script download failed.");
                }
        }
        else {
                audit_log("[INFO] Audit Script found !");
        }

     return $audit_scrpit_path;
}

function audit_log(string $message){

        $epoch = time();
        $datetimeFormat = 'd-m-Y H:i:s';

        $date = new \DateTime();
        $date->setTimestamp($epoch);
        $formated_date = $date->format($datetimeFormat);

        $log_message = "[$formated_date] $message\n";
        $log_file = "/var/log/centreon/get_platform_log_and_info.log";

        file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
}

function download_audit($audit_file){

    //Check the file exists or not
    if(file_exists($audit_file)) {

            //Define header information
            header( "Content-Description: File Transfer" );
            header('Content-Type: text/markdown');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header( "Content-Length: " . filesize($audit_file) );
            header('Content-Disposition: attachment; filename="'.basename($audit_file).'"');
    ob_clean();
    flush();
            readfile($audit_file);
    }
    else{
            audit_log("[ERROR] File downloading failed. File '$audit_file' does not exist");
                        include('error_screen.html');
    }
}

?>