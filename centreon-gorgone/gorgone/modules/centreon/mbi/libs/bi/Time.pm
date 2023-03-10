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

package gorgone::modules::centreon::mbi::libs::bi::Time;

use strict;
use warnings;

# Constructor
# parameters:
# $logger: instance of class CentreonLogger
# $centreon: Instance of centreonDB class for connection to Centreon database
# $centstorage: (optionnal) Instance of centreonDB class for connection to Centstorage database
sub new {
	my $class = shift;
	my $self  = {};
	$self->{logger}	= shift;
	$self->{centstorage} = shift;
	if (@_) {
		$self->{centreon}  = shift;
	}
	$self->{insertQuery} = "INSERT IGNORE INTO `mod_bi_time` (id, hour, day, month_label, month, year, week, dayofweek, utime, dtime) VALUES ";
	bless $self, $class;
	return $self;
}

sub getEntriesDtime {
	my $self = shift;
	my $db = $self->{"centstorage"};
	my $logger = $self->{"logger"};
	
	my ($start, $end) = @_;
	my $query = "SELECT date_format('%Y-%m-%d', dtime) as dtime";
	$query .= " FROM `mod_bi_time`";
	$query .= " WHERE dtime >= '".$start."' AND dtime <'".$end."'";

	my $sth = $db->query({ query => $query });
	my @results = ();
	if (my $row = $sth->fetchrow_hashref()) {
		push @results, $row->{dtime};
	}
	$sth->finish();
	return (@results);
}

sub getEntryID {
	my $self = shift;
	my $db = $self->{"centstorage"};
	my $logger = $self->{"logger"};
	
	my $dtime  = shift;
	my ($interval, $type);
	if (@_) {
		$interval = shift;
		$type = shift;
	}
	my $query = "SELECT `id`, `utime`, date_format(dtime,'%Y-%m-%d') as dtime";
	$query .= " FROM `mod_bi_time`";
	if (!defined($interval)) {
		$query .= " WHERE dtime = '".$dtime."'";
	}else {
		$query .= " WHERE dtime = DATE_ADD('".$dtime."', INTERVAL ".$interval." ".$type.")";
	}
	my $sth = $db->query({ query => $query });
	my @results = ();
	if (my $row = $sth->fetchrow_hashref()) {
		$results[0] = $row->{'id'};
		$results[1] = $row->{'utime'};
	}
	$sth->finish();
	if (!scalar(@results)) {
		$logger->writeLog("ERROR", "Cannot get time ID for date:".$dtime);
	}
	return (@results);
}

sub getDayOfWeek {
	my $self = shift;
	my $db = $self->{"centstorage"};
	my $logger = $self->{"logger"};
	my $date = shift;
	
	my $sth = $db->query({ query => "SELECT LOWER(DAYNAME('".$date."')) as dayOfWeek" });
	my $dayofweek;
	if (my $row = $sth->fetchrow_hashref()) {
		$dayofweek = $row->{"dayOfWeek"};
	}else {
		$logger->writeLog("ERROR", "TIME: Cannot get day of week for date :".$date);
	}
	if (!defined($dayofweek)) {
		$logger->writeLog("ERROR", "TIME: day of week for date ".$date." is null");
	}
	return $dayofweek;
}

sub getYesterdayTodayDate {
	my $self = shift;
	
	# get yesterday date. date format : YYYY-MM-DD
	my $sth = $self->{centstorage}->query({ query => "SELECT CURRENT_DATE() as today, DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY) as yesterday" });

	my $yesterday;
	my $today;
	if (my $row = $sth->fetchrow_hashref()) {
		$yesterday = $row->{yesterday};
		$today =  $row->{today};
	} else {
        $self->{logger}->writeLog('ERROR', "TIME: cannot get yesterday date");
	}
	if (!defined($yesterday)) {
        $self->{logger}->writeLog('ERROR', "TIME: Yesterday start date is null");
	}
	if (!defined($today)) {
        $self->{logger}->writeLog('ERROR', "TIME: today start date is null");
	}
	return ($yesterday, $today);
}

sub addDateInterval {
	my $self = shift;
	my ($date, $interval, $intervalType) = @_;

	# get new date. date format : YYYY-MM-DD
	my $sth = $self->{centstorage}->query({ query => "SELECT DATE_ADD('".$date."', INTERVAL ".$interval." ".$intervalType.") as newDate" });

	my $newDate;
	if (my $row = $sth->fetchrow_hashref()) {
		$newDate = $row->{newDate};
	}
	if (!defined($newDate)) {
        $self->{logger}->writeLog('ERROR', "TIME: DATE_ADD('".$date."', INTERVAL ".$interval." ".$intervalType.") returns null value");
	}
	return $newDate;
}

sub compareDates {
    my $self = shift;
    my ($date1, $date2) = @_;

    my $sth = $self->{centstorage}->query({ query => "SELECT DATEDIFF('".$date1."','".$date2."') as nbDays" });
    if (my $row = $sth->fetchrow_hashref()) {
        return $row->{nbDays};
    }

    $self->{logger}->writeLog('ERROR', "TIME: Cannot compare two dates : ".$date1." and ".$date2);
}

sub insertTimeEntriesForPeriod {
	my $self = shift;
	my $db = $self->{"centstorage"};
	my ($start, $end) = @_;
	
	my $interval = $self->getTotalDaysInPeriod($start, $end) * 24;
	my $counter = 0;
	my $date = "ADDDATE('".$start."',INTERVAL ".$counter." HOUR)";
	my $query_suffix = "";
	while ($counter <= $interval) {
		$query_suffix .= "(UNIX_TIMESTAMP(".$date."),";
		$query_suffix .= "HOUR(".$date."),";
		$query_suffix .= "DAYOFMONTH(".$date."),";
		$query_suffix .=  "LOWER(DATE_FORMAT(".$date.",'%M')),";
		$query_suffix .=  "MONTH(".$date."),";
		$query_suffix .=  "YEAR(".$date."),";
		$query_suffix .=  "WEEK(".$date.", 3),";
		$query_suffix .= "LOWER(DAYNAME(".$date.")),";
		$query_suffix .=  "UNIX_TIMESTAMP(".$date."),"; 
		$query_suffix .=  "".$date."),";
		$counter++;
		$date = "ADDDATE('".$start."',INTERVAL ".$counter." HOUR)";
		if ($counter % 30 == 0) {
			chop($query_suffix);
			$db->query({ query => $self->{insertQuery} . $query_suffix });
			$query_suffix = "";
		}
	}
	chop($query_suffix);
	if ($query_suffix ne "") {
		$db->query({ query => $self->{insertQuery} . $query_suffix });
	}
}

# Delete duplicated entries inserted on winter/summer time change (same timestamp for 02:00 and 03:00)
sub deleteDuplicateEntries {
	my $self = shift;
	my $db = $self->{"centstorage"};
	my ($start, $end) = @_;
	my $query = "SELECT max(id) as id";
	$query .= " FROM mod_bi_time";
	$query .= " WHERE  dtime >='".$start."'";
	$query .= " AND dtime <= '".$end."'";
	$query .= " GROUP BY utime";
	$query .= " HAVING COUNT(utime) > 1";
	my $sth = $db->query({ query => $query });
	my $ids_to_delete = "";
	while (my $row = $sth->fetchrow_hashref()) {
		$ids_to_delete .= $row->{'id'}.",";
	}
	if ($ids_to_delete ne "") {
		chop ($ids_to_delete);
		$db->query({ query => "DELETE FROM mod_bi_time WHERE id IN (".$ids_to_delete.")" });
	}
}

sub getTotalDaysInPeriod {
	my $self = shift;
	my $db = $self->{"centstorage"};
	my $logger = $self->{"logger"};
	my ($start, $end) = @_;

	my $query = "SELECT DATEDIFF('".$end."', '".$start."') diff";
	my $sth = $db->query({ query => $query });
	my $diff;
	if (my $row = $sth->fetchrow_hashref()) {
		$diff = $row->{'diff'};
	}else {
		$logger->writeLog("ERROR", "TIME : Cannot get difference between period start and end");
	}
	if (!defined($diff)){
		$logger->writeLog("ERROR", "TIME : Cannot get difference between period start and end");
	} 
	if($diff == 0) {
		$logger->writeLog("ERROR", "TIME : start date is equal to end date");
	}elsif ($diff < 0) {
		$logger->writeLog("ERROR", "TIME : start date is greater than end date");
	}
	return $diff;
}

sub truncateTable {
	my $self = shift;
	my $db = $self->{"centstorage"};
	
	my $query = "TRUNCATE TABLE `mod_bi_time`";
	$db->query({ query => $query });
	$db->query({ query => "ALTER TABLE `mod_bi_time` AUTO_INCREMENT=1" });
}

sub deleteEntriesForPeriod {
	my $self = shift;
	my $db = $self->{"centstorage"};
	my ($start, $end) = @_;
	
	my $query = "DELETE FROM `mod_bi_time` WHERE dtime >= '".$start."' AND dtime < '".$end."'";
	$db->query({ query => $query });
}

1;
