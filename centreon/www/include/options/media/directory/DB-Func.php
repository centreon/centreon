<?php
/*
 * Centreon is developped with GPL Licence 2.0 :
 * http://www.gnu.org/licenses/old-licenses/gpl-2.0.txt
 * Developped by : Julien Mathis - Romain Le Merlus 
 * 
 * The Software is provided to you AS IS and WITH ALL FAULTS.
 * Centreon makes no representation and gives no warranty whatsoever,
 * whether express or implied, and without limitation, with regard to the quality,
 * any particular or intended purpose of the Software found on the Centreon web site.
 * In no event will Centreon be liable for any direct, indirect, punitive, special,
 * incidental or consequential damages however they may arise and even if Centreon has
 * been previously advised of the possibility of such damages.
 * 
 * For information : contact@centreon.com
 */
	if (!isset ($oreon))
		exit ();
	
	function testDirectoryExistence ($name = NULL)	{
		global $pearDB;
		global $form;
		$id = "";
		if (isset($form))
			$id = $form->getSubmitValue('dir_id');
		$DBRESULT =& $pearDB->query("SELECT dir_name, dir_id FROM view_img_dir WHERE dir_name = '".htmlentities($name, ENT_QUOTES)."'");
		$dir =& $DBRESULT->fetchRow();
		#Modif case
		if ($DBRESULT->numRows() >= 1 && $dir["dir_id"] == $id)	
			return true;
		#Duplicate entry
		else if ($DBRESULT->numRows() >= 1 && $dir["dir_id"] != $id)
			return false;
		else
			return true;
	}

	function deleteDirectoryInDB ($dirs = array())	{
		require_once "./include/options/media/images/DB-Func.php";
		global $pearDB;
		foreach($dirs as $key=>$value)	{
			/*
			 * Purge images of the directory
			 */
			$rq = "SELECT img_img_id FROM view_img_dir_relation WHERE dir_dir_parent_id = '".$key."'";
			$DBRESULT =& $pearDB->query($rq);
			while ($img =& $DBRESULT->fetchRow())
				deleteImgInDB(array($img["img_img_id"]=>$img["img_img_id"]));
			/*
			 * Delete directory
			 */
			$rq = "SELECT dir_alias FROM view_img_dir WHERE dir_id = '".$key."'";
			$DBRESULT =& $pearDB->query($rq);
			$dir_alias =& $DBRESULT->fetchRow();
			rmdir("./img/media/".$dir_alias["dir_alias"]);
			if (!is_dir("./img/media/".$dir_alias["dir_alias"]))	{
				$DBRESULT =& $pearDB->query("DELETE FROM view_img_dir WHERE dir_id = '".$key."'");
			}
		}
	}
	
	function multipleDirectoryInDB ($dirs = array(), $nbrDup = array())	{
		foreach($dirs as $key=>$value)	{
			global $pearDB;
			$DBRESULT =& $pearDB->query("SELECT * FROM view_img_dir WHERE dir_id = '".$key."' LIMIT 1");
			$row =& $DBRESULT->fetchRow();
			$row["dir_id"] = '';
			for ($i = 1; $i <= $nbrDup[$key]; $i++)	{
				$val = null;
				foreach ($row as $key2=>$value2)	{
					$key2 == "dir_name" ? ($dir_name = $value2 = $value2."_".$i) : null;
					$val ? $val .= ", '".$value2."'" : $val .= "'".$value2."'";
				}
				if (testDirectoryExistence($dir_name))	{
					$val ? $rq = "INSERT INTO view_img_dir VALUES (".$val.")" : $rq = null;
					$DBRESULT =& $pearDB->query($rq);
					$DBRESULT =& $pearDB->query("SELECT MAX(dir_id) FROM view_img_dir");
					$maxId =& $DBRESULT->fetchRow();
					if (isset($maxId["MAX(dir_id)"]))	{
						$DBRESULT =& $pearDB->query("SELECT DISTINCT img_img_id FROM view_img_dir_relation WHERE dir_dir_parent_id = '".$key."'");
						while ($img =& $DBRESULT->fetchRow())	{
							$DBRESULT2 =& $pearDB->query("INSERT INTO view_img_dir_relation VALUES ('', '".$maxId["MAX(dir_id)"]."', '".$img["img_img_id"]."')");
						}
					}
				}
			}
		}
	}	
	
	function insertDirectoryInDB ($ret = array())	{
		$dir_id = insertDirectory($ret);
		updateDirectoryElems($dir_id, $ret);
		return $dir_id;
	}
	
	function insertDirectory($ret)	{
		global $form;
		global $pearDB;
		if (!count($ret))
			$ret = $form->getSubmitValues();
		$ret["dir_alias"] = str_replace(" ", "_", $ret["dir_alias"]);
		mkdir("./img/media/".$ret["dir_alias"]);
		if (is_dir("./img/media/".$ret["dir_alias"]))	{			
			$rq = "INSERT INTO view_img_dir ";
			$rq .= "(dir_name, dir_alias, dir_comment) ";
			$rq .= "VALUES ";
			$rq .= "('".htmlentities($ret["dir_name"], ENT_QUOTES)."', '".htmlentities($ret["dir_alias"], ENT_QUOTES)."', '".htmlentities($ret["dir_comment"], ENT_QUOTES)."')";
			$DBRESULT =& $pearDB->query($rq);
			$DBRESULT =& $pearDB->query("SELECT MAX(dir_id) FROM view_img_dir");
			$dir_id =& $DBRESULT->fetchRow();
			return ($dir_id["MAX(dir_id)"]);
		}
		else
			return "";
	}
	
	function updateDirectoryInDB ($dir_id = "")	{
		if (!$dir_id) return;
		updateDirectory($dir_id);
		updateDirectoryElems($dir_id);
	}
	
	function updateDirectory($dir_id = "")	{
		if (!$dir_id) return;
		global $form;
		global $pearDB;
		$ret = array();
		$ret = $form->getSubmitValues();
		$ret["dir_alias"] = str_replace(" ", "_", $ret["dir_alias"]);
		$rq = "SELECT dir_alias FROM view_img_dir WHERE dir_id = '".$dir_id."'";
		$DBRESULT =& $pearDB->query($rq);
		$old_dir_alias =& $DBRESULT->fetchRow();
		if (!is_dir("./img/media/".$old_dir_alias["dir_alias"]))
			mkdir("./img/media/".$old_dir_alias["dir_alias"]);
		rename("./img/media/".$old_dir_alias["dir_alias"], "./img/media/".$ret["dir_alias"]);
		if (is_dir("./img/media/".$ret["dir_alias"]))	{
			$rq = "UPDATE view_img_dir ";
			$rq .= "SET dir_name = '".htmlentities($ret["dir_name"], ENT_QUOTES)."', " .
					"dir_alias = '".htmlentities($ret["dir_alias"], ENT_QUOTES)."', " .
					"dir_comment = '".htmlentities($ret["dir_comment"], ENT_QUOTES)."' " .
					"WHERE dir_id = '".$dir_id."'";
			$DBRESULT =& $pearDB->query($rq);		
		}
	}
	
	function updateDirectoryElems($dir_id, $ret = array())	{
		if (!$dir_id) return;
		global $form;
		global $pearDB;
		$rq = "DELETE FROM view_img_dir_relation ";
		$rq .= "WHERE dir_dir_parent_id = '".$dir_id."'";
		$DBRESULT =& $pearDB->query($rq);
		if (isset($ret["dir_imgs"]))
			$ret = $ret["dir_imgs"];
		else
			$ret = $form->getSubmitValue("dir_imgs");
		for($i = 0; $i < count($ret); $i++)	{
			$rq = "INSERT INTO view_img_dir_relation ";
			$rq .= "(dir_dir_parent_id, img_img_id) ";
			$rq .= "VALUES ";
			$rq .= "('".$dir_id."', '".$ret[$i]."')";
			$DBRESULT =& $pearDB->query($rq);		
		}
	}
?>