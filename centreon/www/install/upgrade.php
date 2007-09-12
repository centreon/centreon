<?
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
	include_once ("../oreon.conf.php");
	include_once ("./step_upgrade/functions.php");
	include_once ("../class/Session.class.php");

	Session::start();

	// Pear Modules Management
	if (file_exists("pear_module.conf.php"))
	   include_once ("pear_module.conf.php");
	
	$DEBUG = 0;

	if (isset($_POST["Recheck"]) && $_POST["step"] == 5)
		 $_POST["step"] = 4;

	if (isset($_POST["goto"]) && !strcmp($_POST["goto"], "Back"))
		 $_POST["step"] -= 2;

	if (isset($_POST["goto-B"]) && !strcmp($_POST["goto-B"], "Back"))
		 $_POST["step"] -= 3;
	
	if (!isset($_POST["step"])){
		include("./step_upgrade/step1.php");
	} else if (isset($_POST["step"]) && $_POST["step"] == 1){
		include("./step_upgrade/step2.php");
	} else if (isset($_POST["step"]) && $_POST["step"] == 2){
		include("./step_upgrade/step3.php");
	} else if (isset($_POST["step"]) && $_POST["step"] == 3){
		include("./step_upgrade/step4.php");
	} else if (isset($_POST["step"]) && $_POST["step"] == 4){
		include("./step_upgrade/step5.php");
	} else if (isset($_POST["step"]) && $_POST["step"] == 5){
		include("./step_upgrade/step6.php");
	} else if (isset($_POST["step"]) && $_POST["step"] == 6){
		include("./step_upgrade/step7.php");
	} else if (isset($_POST["step"]) && $_POST["step"] == 7){
		include("./step_upgrade/step8.php");
	} exit();
?>
