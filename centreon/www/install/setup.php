<?php
/**
Oreon is developped with GPL Licence 2.0 :
http://www.gnu.org/licenses/old-licenses/gpl-2.0.txt
Developped by : Julien Mathis - Romain Le Merlus - Christophe Coraboeuf

The Software is provided to you AS IS and WITH ALL FAULTS.
OREON makes no representation and gives no warranty whatsoever,
whether express or implied, and without limitation, with regard to the quality,
safety, contents, performance, merchantability, non-infringement or suitability for
any particular or intended purpose of the Software found on the OREON web site.
In no event will OREON be liable for any direct, indirect, punitive, special,
incidental or consequential damages however they may arise and even if OREON has
been previously advised of the possibility of such damages.

For information : contact@oreon-project.org
*/
// configuration

	include_once ("../class/Session.class.php");
	include_once ("DB-Func.php");
	Session::start();
	ini_set("track_errors",true);
	if (file_exists("installoreon.conf.php")) {
	   include_once ("installoreon.conf.php");
	}
	// Pear Modules Management
	if (file_exists("pear_module.conf.php")) {
	   include_once ("pear_module.conf.php");
	}

	$DEBUG = 0;
	$msg = NULL;
	$return_false = NULL;

	$pear_path = $conf_installoreon['pear_dir'];


	if (isset($_POST["step"]) && $_POST["step"] == 4 && isset($_POST["Recheck"]))
		 $_POST["step"] = 3;
	if (isset($_POST["step"]) && $_POST["step"] == 5 && isset($_POST["Recheck"]))
		 $_POST["step"] = 4;
	if (isset($_POST["step"]) && $_POST["step"] == 7 && isset($_POST["Recheck"]))
		 $_POST["step"] = 6;
	if (isset($_POST["step"]) && $_POST["step"] == 10 && isset($_POST["Recheck"]))
		 $_POST["step"] = 9;
/*	if (isset($_POST["install_missing_pear_module"]) && isset($_POST["pear_module"]) && $_POST["step"] == 5) {
/		 $_POST["step"] = 4;

		exec('sudo pear install '. $pear_module["$package_file"]);

	}*/

	if (isset($_POST["goto"]) && !strcmp($_POST["goto"], "Back"))
		 $_POST["step"] -= 2;
	if (isset($_POST["step"]) && $_POST["step"] == 6 && isset($_POST["pwdOreonDB"]) && strcmp($_POST["pwdOreonDB"], $_POST["pwdOreonDB2"])){
		$_POST["step"] = 5;
		$passwd_error = "Password not confirmed correctly.";
	}
	if (isset($_POST["step"]) && $_POST["step"] == 7 && isset($_POST["oreonpasswd"])  && strcmp($_POST["oreonpasswd"], $_POST["oreonpasswd2"])){
		$_POST["step"] = 6;
		$passwd_error = "Password not confirmed correctly.";
	}

	if (!isset($_POST["step"]))
		include("./steps/step1.php");
	else if (isset($_POST["step"]) && $_POST["step"] == 1)
		include("./steps/step2.php");
	else if (isset($_POST["step"]) && $_POST["step"] == 2)
		include("./steps/step3.php");
	else if (isset($_POST["step"]) && $_POST["step"] == 3)
		include("./steps/step4.php");
	else if (isset($_POST["step"]) && $_POST["step"] == 4)
		include("./steps/step5.php");
	else if (isset($_POST["step"]) && $_POST["step"] == 5)
		include("./steps/step6.php");
	else if (isset($_POST["step"]) && $_POST["step"] == 6)
		include("./steps/step7.php");
	else if (isset($_POST["step"]) && $_POST["step"] == 7)
		include("./steps/step8.php");
	else if (isset($_POST["step"]) && $_POST["step"] == 8)
		include("./steps/step9.php");
	else if (isset($_POST["step"]) && $_POST["step"] == 9)
		include("./steps/step10.php");
	else if (isset($_POST["step"]) && $_POST["step"] == 10)
		include("./steps/step11.php");
	else if (isset($_POST["step"]) && $_POST["step"] == 11)
		include("./steps/step12.php");
	else if (isset($_POST["step"]) && $_POST["step"] == 12)
		include("./steps/step13.php");
		ini_set("track_errors",false);
	exit();
?>
