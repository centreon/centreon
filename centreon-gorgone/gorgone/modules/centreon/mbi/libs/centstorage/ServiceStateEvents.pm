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

package gorgone::modules::centreon::mbi::libs::centstorage::ServiceStateEvents;

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
	$self->{"biServiceStateEventsObj"} = shift;
	$self->{"timePeriodObj"} = shift;
	if (@_) {
		$self->{"centreon"}  = shift;
	}
	
	$self->{"name"} = "servicestateevents";
	$self->{"timeColumn"} = "end_time";
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

sub agreggateEventsByTimePeriod {
	my ($self, $timeperiodList, $start, $end, $liveServiceByTpId, $mode) = @_;
	my $db = $self->{"centstorage"};
	my $logger = $self->{"logger"};
		
	my $rangesByTP = ($self->{"timePeriodObj"})->getTimeRangesForPeriodAndTpList($timeperiodList, $start, $end);
	my $query = "SELECT e.host_id,e.service_id, start_time, end_time, ack_time, state, last_update";
	$query .= " FROM `servicestateevents` e";
	$query .= " RIGHT JOIN (select host_id,service_id from mod_bi_tmp_today_services group by host_id,service_id) t2";
	$query .= " ON e.host_id = t2.host_id AND e.service_id = t2.service_id";
	$query .= " WHERE start_time < ".$end."";
	$query .= " AND end_time > ".$start."";
	$query .= " AND in_downtime = 0 ";
	$query .= " ORDER BY start_time ";

	my $serviceEventObjects = $self->{"biServiceStateEventsObj"};
	my $sth = $db->query($query);
	$serviceEventObjects->createTempBIEventsTable();
	$serviceEventObjects->prepareTempQuery();
	
	while (my $row = $sth->fetchrow_hashref()) {
		if (!defined($row->{'end_time'})) {
			$row->{'end_time'} = $end;
		}
		while (my ($timeperiodID, $timeRanges) = each %$rangesByTP) {
			my @tab = ();
			$tab[0] = $row->{'host_id'};
			$tab[1] = $row->{'service_id'};
			$tab[2] = $liveServiceByTpId->{$timeperiodID};
			$tab[3] = $row->{'state'};
			if ($mode eq 'daily') {
				$timeRanges = ($self->{"timePeriodObj"})->getTimeRangesForPeriod($timeperiodID, $row->{'start_time'}, $row->{'end_time'});
			}
			($tab[4], $tab[5]) = $self->processIncidentForTp($timeRanges,$row->{'start_time'}, $row->{'end_time'});
			$tab[6] = $row->{'end_time'};
			$tab[7] = defined($row->{ack_time}) ? $row->{ack_time} : 0;
			$tab[8] = $row->{last_update};
			if (defined($tab[4]) && $tab[4] != -1) {
				$serviceEventObjects->bindParam(\@tab);
			}
		}
	}
	($db->getInstance)->commit;
}

sub processIncidentForTp {
	my ($self, $timeRanges, $start, $end) = @_;
	my $db = $self->{"centstorage"};
	my $logger = $self->{"logger"};
	
	my $rangeSize = scalar(@$timeRanges);
	my $duration = 0;
	my $slaDuration = 0;
	my $range = 0;
	my $i = 0;
	my $processed = 0;
	my $slaStart = $start;
	my $slaStartModified = 0;

	foreach(@$timeRanges) {
		my $currentStart = $start;
		my $currentEnd = $end;
    	$range = $_;
		my ($rangeStart, $rangeEnd) = ($range->[0], $range->[1]);
		if ($currentStart < $rangeEnd && $currentEnd > $rangeStart) {
			$processed = 1;
			if ($currentStart > $rangeStart) {
				$slaStartModified = 1;
			} elsif ($currentStart < $rangeStart) {
    			$currentStart = $rangeStart;
    			if (!$slaStartModified) {
    				$slaStart = $currentStart;
    				$slaStartModified = 1;
    			}
    		}
	    	if ($currentEnd > $rangeEnd) {
    			$currentEnd = $rangeEnd;
    		}
    		$slaDuration += $currentEnd - $currentStart;
    	}
	}
	if (!$processed) {
		return (-1, -1, -1);
	}
	return ($slaStart, $slaDuration);
}

sub dailyPurge {
	my $self = shift;
	my $db = $self->{"centstorage"};
	my $logger = $self->{"logger"};
	my ($end) = @_;
	
	$logger->writeLog("DEBUG", "[PURGE] [servicestateevents] purging data older than ".$end);
	my $query = "DELETE FROM `servicestateevents` where end_time < UNIX_TIMESTAMP('".$end."')";
	$db->query($query);
}

sub getNbEvents {
	my $self = shift;
	my $db = $self->{"centstorage"};
	my ($start, $end) = @_;
	my $nbEvents = 0;
	my $logger = $self->{"logger"};
	
	my $query = "SELECT count(*) as nbEvents";
	$query .= " FROM `servicestateevents` e";
	$query .= " RIGHT JOIN (select host_id,service_id from mod_bi_tmp_today_services group by host_id,service_id) t2";
	$query .= " ON e.host_id = t2.host_id AND e.service_id = t2.service_id";
	$query .= " WHERE start_time < ".$end."";
	$query .= " AND end_time > ".$start."";
	$query .= " AND in_downtime = 0 ";
	
	my $sth = $db->query($query);

	while (my $row = $sth->fetchrow_hashref()) {
		$nbEvents = $row->{'nbEvents'};
	}
	return $nbEvents;
}

1;
