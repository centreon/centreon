###################################################################
# Centreon is developped with GPL Licence 2.0 
#
# GPL License: http://www.gnu.org/licenses/old-licenses/gpl-2.0.txt
#
# Developped by : Julien Mathis - jmathis@merethis.com
#
###################################################################
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; either version 2
# of the License, or (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
# 
#    For information : contact@merethis.com
####################################################################

# Identify concerned service
# need parameter hostname and service description
sub identify_service($$){
	CheckMySQLConnexion();
	while (!$con_ods->ping){;}
	if ($con_ods->ping){
		writeLogFile("SELECT id, storage_type, must_be_rebuild FROM index_data WHERE host_name = '".$_[0]."' AND service_description = '".$_[1]."'\n");
	    my $sth1 = $con_ods->prepare("SELECT id, storage_type, must_be_rebuild FROM index_data WHERE host_name = '".$_[0]."' AND service_description = '".$_[1]."'");
	    if (!$sth1->execute) {
	    	writeLogFile("Error:" . $sth1->errstr . "\n");
	    }
	    writeLogFile("Rows Number : ".$sth1->rows()."\n");
	    # IF service unknown, insert it.
	    if ($sth1->rows() == 0){
	    	writeLogFile("entered !! \n");
			if ($_[0] && $_[1]){
				writeLogFile("entered 2 !! \n");
				$host_id = getHostID($_[0]);
				writeLogFile("host_id : $host_id \n");
				if (defined($host_id) && $host_id ne 0){
					writeLogFile("entered 3 !! \n");
					$service_id = getServiceID($host_id, $_[1]);
					writeLogFile("Service ID : $service_id \n");
					if ($service_id){
						$sth1 = $con_ods->prepare("SELECT * FROM `index_data` WHERE `host_id` = '".$host_id."' AND `service_id` = '".$service_id."'");
						if (!$sth1->execute) {
							writeLogFile("Error:" . $sth1->errstr . "\n");
						}
						writeLogFile($sth1->rows()."\n");
						if ($sth1->rows() == 0){
							writeLogFile("INSERT INTO `index_data` (`host_name`, `host_id`, `service_description`, `service_id`) VALUES ('".$_[0]."', '".$host_id."', '".$_[1]."', '".$service_id."')");
							$sth1 = $con_ods->prepare(	"INSERT INTO `index_data` (`host_name`, `host_id`, `service_description`, `service_id`) ".
														"VALUES ('".$_[0]."', '".$host_id."', '".$_[1]."', '".$service_id."')");
							if (!$sth1->execute) {
								writeLogFile("Error:" . $sth1->errstr . "\n");
							}
						} else {
							$sth1 = $con_ods->prepare("UPDATE `index_data` SET `host_name` = '".$_[0]."' , `service_description` = '".$_[1]."' where `host_id` = '".$host_id."' AND `service_id` = '".$service_id."'");
							if (!$sth1->execute) {writeLogFile("Error:" . $sth1->errstr . "\n");}
						}
						undef($sth1);
					}
				}
			}
		    $sth1 = $con_ods->prepare("SELECT id, storage_type FROM index_data WHERE host_name = '".$_[0]."' AND service_description = '".$_[1]."'");
		    if (!$sth1->execute) {writeLogFile("Error:" . $sth1->errstr . "\n");}
	    }
	    undef($host_id);
	    undef($service_id);
	    my $data = $sth1->fetchrow_hashref();
	    undef($sth1);
	    my @data_return = ($data->{'id'}, $data->{'storage_type'}, $data->{'must_be_rebuild'});
	    undef($data);
	    return @data_return;
	}
}

sub identify_hidden_service($$){
	CheckMySQLConnexion();
	while (!$con_ods->ping){;}
	if ($con_ods->ping){
		my $sth1 = $con_ods->prepare("SELECT id, storage_type, must_be_rebuild FROM index_data WHERE host_name = '".$_[0]."' AND service_description = '".$_[1]."'");
	    if (!$sth1->execute) {writeLogFile("Error : " . $sth1->errstr . "\n");}
	    # IF service unknown, insert it.
	    if ($sth1->rows() == 0){
			if ($_[0] && $_[1]){
				$sth1 = $con_ods->prepare("INSERT INTO `index_data` (`host_name`, `service_description`, `special`) VALUES ('".$_[0]."', '".$_[1]."', '1')");
				if (!$sth1->execute) {writeLogFile("Error : " . $sth1->errstr . "\n");}
				undef($sth1);
			}
		    $sth1 = $con_ods->prepare("SELECT id, storage_type FROM index_data WHERE host_name = '".$_[0]."' AND service_description = '".$_[1]."'");
		    if (!$sth1->execute) {writeLogFile("Error : " . $sth1->errstr . "\n");}
	    }
	    my $data = $sth1->fetchrow_hashref();
	    undef($sth1);
	    my @data_return = ($data->{'id'}, $data->{'storage_type'}, $data->{'must_be_rebuild'});
	    undef($data);
	    return @data_return;
	}
}
1;