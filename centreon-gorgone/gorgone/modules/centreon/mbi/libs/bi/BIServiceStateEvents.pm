# 
# Copyright 2019 Centreon (http://www.centreon.com/)
#
# Centreon is a full-fledged industry-strength solution that meets
# the needs in IT infrastructure and application monitoring for
# service performance.
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
#     http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.
#

use strict;
use warnings;

package gorgone::modules::centreon::mbi::libs::bi::BIServiceStateEvents;

# Constructor
# parameters:
# $logger: instance of class CentreonLogger
# $centreon: Instance of centreonDB class for connection to Centreon database
# $centstorage: (optionnal) Instance of centreonDB class for connection to Centstorage database
sub new {
	my $class = shift;
	my $self  = {};
	$self->{"logger"}	= shift;
	$self->{"centstorage"} = shift;
	$self->{'timeperiod'} = shift;
	$self->{'bind_counter'} = 0;
	$self->{'name'} = "mod_bi_servicestateevents";
	$self->{'tmp_name'} = "mod_bi_servicestateevents_tmp";
	$self->{'timeColumn'} = "end_time";
	bless $self, $class;
	return $self;
}

sub getName() {
	my $self = shift;
	return $self->{'name'};
}

sub getTimeColumn() {
	my $self = shift;
	return $self->{'timeColumn'};
}

sub prepareQuery {
	my $self = shift;
	my $db = $self->{"centstorage"};

	my $query = "INSERT INTO `".$self->{'name'}."`".
        " (`modbiservice_id`, `modbiliveservice_id`,".
        " `state`, `start_time`, `sla_duration`,".
        " `end_time`,  `ack_time`, `last_update`, `duration`) ".
        " VALUES (?,?,?,?,?,?,?,?, TIMESTAMPDIFF(SECOND, FROM_UNIXTIME(?), FROM_UNIXTIME(?)))";
	$self->{'statement'} = $db->prepare($query);
	$self->{'dbinstance'} = $db->getInstance;
	($self->{'dbinstance'})->begin_work;
}

sub createTempBIEventsTable {
	my ($self) = @_;
	my $db = $self->{"centstorage"};
	$db->query({ query => "DROP TABLE IF EXISTS `mod_bi_servicestateevents_tmp`" });
	my $createTable = " CREATE TABLE `mod_bi_servicestateevents_tmp` (";
	$createTable .= " `host_id` int(11) NOT NULL,";
	$createTable .= " `service_id` int(11) NOT NULL,";
	$createTable .= " `modbiliveservice_id` tinyint(4) NOT NULL,";
	$createTable .= " `state` tinyint(4) NOT NULL,";
	$createTable .= " `start_time` int(11) NOT NULL,";
	$createTable .= " `end_time` int(11) DEFAULT NULL,";
	$createTable .= " `duration` int(11) NOT NULL,";
	$createTable .= " `sla_duration` int(11) NOT NULL,";
	$createTable .= " `ack_time` int(11) DEFAULT NULL,";
	$createTable .= " `last_update` tinyint(4) DEFAULT '0',";
	$createTable .= " KEY `modbiservice_id` (`host_id`,`service_id`)";
	$createTable .= " ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
	$db->query({ query => $createTable });
}

sub prepareTempQuery {
	my $self = shift;
	my $db = $self->{"centstorage"};

	my $query = "INSERT INTO `".$self->{'tmp_name'}."`".
        " (`host_id`,`service_id`,`modbiliveservice_id`,".
        " `state`, `start_time`, `sla_duration`,".
        " `end_time`,  `ack_time`, `last_update`, `duration`) ".
        " VALUES (?,?,?,?,?,?,?,?,?, TIMESTAMPDIFF(SECOND, FROM_UNIXTIME(?), FROM_UNIXTIME(?)))";
	$self->{'statement'} = $db->prepare($query);
	$self->{'dbinstance'} = $db->getInstance;
	($self->{'dbinstance'})->begin_work;
}

sub bindParam {
	my ($self, $row) = @_;
	my $db = $self->{"centstorage"};
	my $logger = $self->{"logger"};
	
	my $size = scalar(@$row);
	my $sth = $self->{'statement'};
	for (my $i = 0; $i < $size; $i++) {
        $sth->bind_param($i + 1, $row->[$i]);
	}
	$sth->bind_param($size + 1, $row->[4]);
	$sth->bind_param($size + 2, $row->[6]);

	($self->{'statement'})->execute;
	if (defined(($self->{'dbinstance'})->errstr)) {
  		$logger->writeLog("FATAL", $self->{'name'}." insertion execute error : ".($self->{'dbinstance'})->errstr);
	}
	if ($self->{'bind_counter'} >= 1000) {
		$self->{'bind_counter'} = 0;
		($self->{'dbinstance'})->commit;
		if (defined(($self->{'dbinstance'})->errstr)) {
  			$logger->writeLog("FATAL", $self->{'name'}." insertion commit error : ".($self->{'dbinstance'})->errstr);
		}
		($self->{'dbinstance'})->begin_work;
	}
	$self->{'bind_counter'} += 1;
	
}

sub getDayEvents {
	my $self = shift;
	my $db = $self->{"centstorage"};
	my $timeperiod = $self->{'timeperiod'};
	my ($start, $end, $liveserviceId, $ranges) = @_;
	my $liveServiceList = shift;
	my %results = ();

	my $query = "SELECT start_time, end_time, state, modbiservice_id";
	$query .= " FROM `" . $self->{'name'} . "`";
	$query .= " WHERE `start_time` < " . $end;
    $query .= " AND `end_time` > " . $start;
    $query .= " AND `state` IN (0,1,2,3)";
    $query .= " AND modbiliveservice_id=" . $liveserviceId;
	my $sth = $db->query({ query => $query });

	if (!scalar(@$ranges)) {
		return \%results;
	}

    my $rows = [];
    while (my $row = (
            shift(@$rows) ||
            shift(@{$rows = $sth->fetchall_arrayref(undef,10_000) || []}) ) 
        ) {
        my $entryID = $row->[3];

        my ($started, $ended) = (0,0);
        my $rangeSize = scalar(@$ranges);
        my $eventDuration = 0;
        for (my $count = 0; $count < $rangeSize; $count++) {
            my $currentStart = $row->[0];
            my $currentEnd = $row->[1];
				
            my $range = $ranges->[$count];
            my ($rangeStart, $rangeEnd) = ($range->[0], $range->[1]);
            if ($currentStart < $rangeEnd && $currentEnd > $rangeStart) {
                if ($currentStart < $rangeStart) {
                    $currentStart = $rangeStart;
                } elsif ($count == 0) { 
                    $started = 1;
                }
                if ($currentEnd > $rangeEnd) {
                    $currentEnd = $rangeEnd;
                } elsif ($count == $rangeSize - 1) {
                    $ended = 1;
                }
                $eventDuration += $currentEnd - $currentStart;
            }
        }
        if (!defined($results{$entryID})) {
            my @tab = (0, 0, 0, 0, 0, 0, 0, 0, 0);

            #New table - sync with the real table in centreon_storage database
            #  0: OK time , 1: CRITICAL time, 2 : DEGRADED time 3 : alert_unavailable_opened
            #  4: alert unavailable_closed 5 : alert_degraded_opened  6 : alertes_degraded_closed
            #  7 : alert_unknown_opened 8 : alert_unknown_closed
            $results{$entryID} = \@tab;
        }
        my $stats = $results{$entryID};
        my $state = $row->[2];
        if ($state == 0) {
            $stats->[0] += $eventDuration;
        } elsif ($state == 1) {
            $stats->[2] += $eventDuration;
            $stats->[5] += $started;
            $stats->[6] += $ended;
        } elsif ($state == 2) {
            $stats->[1] += $eventDuration;
            $stats->[3] += $started;
            $stats->[4] += $ended;
        } else {
            $stats->[7] += $started;
            $stats->[8] += $ended;
        }
        $results{$entryID} = $stats;
    }

    return (\%results);
}

#Deprecated
sub getNbEvents {
	my ($self, $start, $end, $groupId, $hcatId, $scatId, $liveServiceID) = @_;
	my $db = $self->{"centstorage"};
	
	my $query = "SELECT count(state) as nbEvents, state";
	$query .= " FROM mod_bi_services s, ".$self->{'name'}." e";
	$query .= " WHERE s.hg_id = ".$groupId." AND s.hc_id=".$hcatId." AND s.sc_id=".$scatId;
	$query .= " AND s.id = e.modbiservice_id";
	$query .= " AND start_time < UNIX_TIMESTAMP('".$end."')";
	$query .= " AND end_time > UNIX_TIMESTAMP('".$start."')";
	$query .= " AND e.modbiliveservice_id=".$liveServiceID;
	$query .= " AND e.state in (1,2,3)";
	$query .= " GROUP BY e.state";
	my $sth = $db->query({ query => $query });
	
	my ($warnEvents, $criticalEvents, $otherEvents) = (undef, undef, undef);
	while (my $row = $sth->fetchrow_hashref()) {
		if ($row->{'state'} == 1) {
			$warnEvents = $row->{'nbEvents'};
		}elsif ($row->{'state'} == 2) {
			$criticalEvents = $row->{'nbEvents'};
		}else {
			$otherEvents = $row->{'nbEvents'};
		}
	}
	return ($warnEvents, $criticalEvents, $otherEvents);
}

sub deleteUnfinishedEvents {
	my $self = shift;
	my $db = $self->{"centstorage"};
	
	my $query = "DELETE FROM `".$self->{'name'}."`";
	$query .= " WHERE last_update = 1 OR end_time is null";
	$db->query({ query => $query });
}

1;
