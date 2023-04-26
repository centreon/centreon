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
use Time::Local;
use gorgone::modules::centreon::mbi::libs::Utils;
 
package gorgone::modules::centreon::mbi::libs::centreon::Timeperiod;

# Constructor
# parameters:
# $logger: instance of class CentreonLogger
# $centreon: Instance of centreonDB class for connection to Centreon database
# $centstorage: (optionnal) Instance of centreonDB class for connection to Centstorage database
sub new {
	my $class = shift;
	my $self  = {};
	$self->{"logger"}	= shift;
	$self->{"centreon"} = shift;
	if (@_) {
		$self->{"centstorage"}  = shift;
	}
	bless $self, $class;
	return $self;
}

sub getTimeRangesForDay {
	my $self = shift;
	my $db = $self->{"centreon"};
	my ($weekDay, $name, $unixtime) = @_;
   	my @results = ();

	my @weekDays = ("sunday", "monday", "tuesday", "wednesday", "thursday", "friday", "saturday");
	my $query = "SELECT tp_" . $weekDay;
	$query .= " FROM timeperiod";
	$query .= " WHERE tp_name = '" . $name . "'";
	my $sth = $db->query({ query => $query });
    if (my $row = $sth->fetchrow_hashref()) {
    	if (defined($row->{'tp_'.$weekDay})) {
			my @ranges = split(",", $row->{'tp_' . $weekDay});
			foreach (@ranges) {
				my ($start, $end) = split("-", $_);
				my ($start_hour, $start_min) = split(':', $start);
				my ($end_hour, $end_min) = split(':', $end);
				my @range = ($unixtime+ $start_hour * 60 * 60 + $start_min * 60, $unixtime + $end_hour * 60 * 60 + $end_min * 60);
				$results[scalar(@results)] = \@range;
			}
    	}
	}

	return (\@results);
}

sub getTimeRangesForDayByDateTime {
	my $self = shift;
	my $db = $self->{"centreon"};
	my ($name, $dateTime, $weekDay) = @_;
   	my @results = ();

	my $query = "SELECT tp_".$weekDay;
	$query .= " FROM timeperiod";
	$query .= " WHERE  tp_name='".$name."'";
	my $sth = $db->query({ query => $query });
    if(my $row = $sth->fetchrow_hashref()) {
    	if (defined($row->{'tp_'.$weekDay})) {
			my @ranges = split(",", $row->{'tp_'.$weekDay});
			foreach(@ranges) {
				my ($start, $end) = split("-", $_);
				my $range_end = "'".$dateTime." ".$end.":00'";
				if ($end eq '24:00') {
					$range_end = "DATE_ADD('".$dateTime."', INTERVAL 1 DAY)";
				}
				my @range =  ("'".$dateTime." ".$start.":00'", $range_end);
				$results[scalar(@results)] = \@range;
			}
    	}
	}
	$sth->finish();
		
	return (\@results);
}

sub getRangeTable {
	my ($self, $rangeStr) = @_;
	if (!defined($rangeStr)) {
		$rangeStr = "";
	}
	my @ranges = split(",", $rangeStr);
	
	my @results = ();
	foreach(@ranges) {
		my ($start, $end) = split("-", $_);
		my ($start_hour, $start_min) = split(":", $start);
		my ($end_hour, $end_min) = split(":", $end);
		push @results, [$start_hour * 60 * 60 + $start_min * 60, $end_hour * 60 * 60 + $end_min * 60];
	}
	return [@results];
}

sub getAllRangesForTpId {
	my ($self, $timeperiod_id) = @_;
	my $db = $self->{"centreon"};
	my $logger = $self->{"logger"};
	my $query = "SELECT tp_monday, tp_tuesday, tp_wednesday, tp_thursday, tp_friday, tp_saturday, tp_sunday";
	$query .= " FROM timeperiod";
	$query .= " WHERE  tp_id='".$timeperiod_id."'";
	my $sth = $db->query({ query => $query });
	
	my @results = ();
	if(my $row = $sth->fetchrow_hashref()) {
		$results[0] = $self->getRangeTable($row->{'tp_sunday'});
		$results[1] = $self->getRangeTable($row->{'tp_monday'});
		$results[2] = $self->getRangeTable($row->{'tp_tuesday'});
		$results[3] = $self->getRangeTable($row->{'tp_wednesday'});
		$results[4] = $self->getRangeTable($row->{'tp_thursday'});
		$results[5] = $self->getRangeTable($row->{'tp_friday'});
		$results[6] = $self->getRangeTable($row->{'tp_saturday'});
	}else {
		$logger->writeLog("ERROR", "Cannot find time period with id '".$timeperiod_id."' in Centreon Database");
	}
	return [@results];
}

sub getTimeRangesForPeriod {
	my $self = shift;
	my ($timeperiodId, $start, $end) = @_;
   	my @results = ();
   	my @weekDays = ("sunday", "monday", "tuesday", "wednesday", "thursday", "friday", "saturday");
   	my $days = gorgone::modules::centreon::mbi::libs::Utils->getRebuildPeriods($start, $end);
   	my $weekRanges = $self->getAllRangesForTpId($timeperiodId);
   	foreach (@$days) {
   		my $dayStart = $_->{'start'};
   		my $dayRanges = $weekRanges->[(localtime($dayStart))[6]];
   		foreach(@$dayRanges) {
   			push @results, [$dayStart+$_->[0], $dayStart+$_->[1]];
   		}
   	}
   	return [@results];
}

sub getTimeRangesForPeriodAndTpList {
	my $self = shift;
	my ($timeperiodList, $start, $end) = @_;
	
	my %rangesByTP = ();
	while (my ($key, $value) = each %$timeperiodList) {
		$rangesByTP{$key} = $self->getTimeRangesForPeriod($key, $start, $end);
	}
	return \%rangesByTP;
}

sub getId {
	my $self = shift;
	my $db = $self->{"centreon"};
	my $name = shift;
	
	my $query = "SELECT tp_id";
	$query .= " FROM timeperiod";
	$query .= " WHERE tp_name = '".$name."'";
	my $sth = $db->query({ query => $query });
	my $result = -1;
    if(my $row = $sth->fetchrow_hashref()) {
    	$result = $row->{'tp_id'};
    }
    return $result;
}

sub getPeriodsLike {
	my $self = shift;
	my $db = $self->{"centreon"};
	my $name = shift;
	
	my $query = "SELECT tp_id, tp_name";
	$query .= " FROM timeperiod";
	$query .= " WHERE tp_name like '".$name."%'";
	my $sth = $db->query({ query => $query });
	my %result = ();
    while (my $row = $sth->fetchrow_hashref()) {
    	$result{$row->{'tp_id'}} = $row->{'tp_name'};
    }
    return \%result;
}

sub getPeriods {
	my $self = shift;
	my $db = $self->{"centreon"};
	my $logger = $self->{'logger'};
	my $ids = shift;
	
	my $idStr = "";
	
	foreach my $key (keys %$ids) {
		if ($idStr eq "") {
		$idStr .= $key;			
		}else {
			$idStr .= ",".$key;
		}
	}
	if ($idStr eq "") {
		$logger->writeLog("ERROR", "Select a timeperiod in the ETL configuration menu");
	}
	my $query = "SELECT tp_id, tp_name";
	$query .= " FROM timeperiod";
	$query .= " WHERE tp_id IN (".$idStr.")";
	my $sth = $db->query({ query => $query });
	my %result = ();
    while (my $row = $sth->fetchrow_hashref()) {
    	$result{$row->{'tp_id'}} = $row->{'tp_name'};
    }
    return \%result;
}

sub getCentilePeriods {
	my $self = shift;
	my $db = $self->{"centreon"};
	my $logger = $self->{'logger'};

	my $query = "SELECT tp_id, tp_name";
	$query .= " FROM timeperiod";
	$query .= " WHERE tp_id IN (select timeperiod_id from mod_bi_options_centiles)";
	my $sth = $db->query({ query => $query });
	my %result = ();
    while (my $row = $sth->fetchrow_hashref()) {
    	$result{$row->{'tp_id'}} = $row->{'tp_name'};
    }
    return \%result;
}

1;
