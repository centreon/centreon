<?php

function getColors($db) {
  $stateColors = array(0 => "#19EE11",
		       1 => "#F91E05",
		       2 => "#82CFD8",
		       4 => "#2AD1D4");
  
  // Get configured colors
  $res = $db->query("SELECT `key`, `value` FROM `options` WHERE `key` LIKE 'color%'");
  while ($row = $res->fetchRow()) {
    if ($row['key'] == "color_up") {
      $stateColors[0] = $row['value'];
    } elseif ($row['key'] == "color_down") {
      $stateColors[1] = $row['value'];
    } elseif ($row['key'] == "color_unreachable") {
      $stateColors[2] = $row['value'];
    } elseif ($row['key'] == "color_pending") {
      $stateColors[4] = $row['value'];
    }
  }
  return $stateColors;
}

function getLabels() {
  return array(0 => "Up",
	       1 => "Down",
	       2 => "Unreachable",
	       4 => "Pending");
}