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
use POSIX;
use Time::Local;
use Tie::File;
use DateTime;

package gorgone::modules::centreon::mbi::libs::Utils;

sub new {
    my $class = shift;
    my $self  = {};
    bless $self, $class;

    $self->{logger}	= shift;
    $self->{tz} = DateTime::TimeZone->new(name => 'local')->name();
    return $self;
}

sub checkBasicOptions {
    my ($self, $options) = @_;

    # check execution mode daily to extract yesterday data or rebuild to get more historical data
    if (($options->{daily} == 0 && $options->{rebuild} == 0 && (!defined($options->{create_tables}) || $options->{create_tables} == 0) && (!defined($options->{centile}) || $options->{centile} == 0))
        || ($options->{daily} == 1 && $options->{rebuild} == 1)) {
        $self->{logger}->writeLogError("Specify one execution method. Check program help for more informations");
        return 1;
    }

    # check if options are set correctly for rebuild mode
    if (($options->{rebuild} == 1 || (defined($options->{create_tables}) && $options->{create_tables} == 1))
        && ($options->{start} ne '' && $options->{end} eq '') 
        || ($options->{start} eq '' && $options->{end} ne '')) {
        $self->{logger}->writeLogError("Specify both options --start and --end or neither of them to use default data retention options");
        return 1;
    }
    # check start and end dates format
    if ($options->{rebuild} == 1 && $options->{start} ne '' && $options->{end} ne '' 
        && !$self->checkDateFormat($options->{start}, $options->{end})) {
        $self->{logger}->writeLogError("Verify period start or end date format");
        return 1;
    }

    return 0;
}

sub buildCliMysqlArgs {
    my ($self, $con) = @_;

    my $args = '-u "' . $con->{user} . '" ' .
        '-p"' . $con->{password} . '" ' . 
        '-h "' . $con->{host} . '" ' .
		'-P ' . $con->{port};
    return $args;
}

sub getYesterdayTodayDate {
    my ($self) = @_;

    my $dt = DateTime->from_epoch(
        epoch     => time(),
        time_zone => $self->{tz}
    );

    my $month = $dt->month();
    $month = '0' . $month if ($month < 10);
    my $day = $dt->day();
    $day = '0' . $day if ($day < 10);
    my $today = $dt->year() . '-' . $month . '-' . $day;

    $dt->subtract(days => 1);
    $month = $dt->month();
    $month = '0' . $month if ($month < 10);
    $day = $dt->day();
    $day = '0' . $day if ($day < 10);
    my $yesterday = $dt->year() . '-' . $month . '-' . $day;

	return ($yesterday, $today);
}

sub subtractDateMonths {
    my ($self, $date, $num) = @_;

    if ($date !~ /(\d{4})-(\d{2})-(\d{2})/) {
        $self->{logger}->writeLog('ERROR', "Verify date format");
    }

    my $dt = DateTime->new(year => $1, month => $2, day => $3, hour => 0, minute => 0, second => 0, time_zone => $self->{tz})->subtract(months => $num);

    my $month = $dt->month();
    $month = '0' . $month if ($month < 10);
    my $day = $dt->day();
    $day = '0' . $day if ($day < 10);
    return $dt->year() . '-' . $month . '-' . $day;
}

sub subtractDateDays {
    my ($self, $date, $num) = @_;

    if ($date !~ /(\d{4})-(\d{2})-(\d{2})/) {
        $self->{logger}->writeLog('ERROR', "Verify date format");
    }

    my $dt = DateTime->new(year => $1, month => $2, day => $3, hour => 0, minute => 0, second => 0, time_zone => $self->{tz})->subtract(days => $num);

    my $month = $dt->month();
    $month = '0' . $month if ($month < 10);
    my $day = $dt->day();
    $day = '0' . $day if ($day < 10);
    return $dt->year() . '-' . $month . '-' . $day;
}

sub getDayOfWeek {
    my ($self, $date) = @_;

    if ($date !~ /(\d{4})-(\d{2})-(\d{2})/) {
        $self->{logger}->writeLog('ERROR', "Verify date format");
    }

    return lc(DateTime->new(year => $1, month => $2, day => $3, hour => 0, minute => 0, second => 0, time_zone => $self->{tz})->day_name());
}

sub getDateEpoch {
    my ($self, $date) = @_;

    if ($date !~ /(\d{4})-(\d{2})-(\d{2})/) {
        $self->{logger}->writeLog('ERROR', "Verify date format");
    }

    my $epoch = DateTime->new(year => $1, month => $2, day => $3, hour => 0, minute => 0, second => 0, time_zone => $self->{tz})->epoch();
    $date =~ s/-//g;

    return wantarray ? ($epoch, $date) : $epoch;
}

sub getRangePartitionDate {
    my ($self, $start, $end) = @_;

    if ($start !~ /(\d{4})-(\d{2})-(\d{2})/) {
        $self->{logger}->writeLog('ERROR', "Verify period start format");
    }
    my $dt1 = DateTime->new(year => $1, month => $2, day => $3, hour => 0, minute => 0, second => 0, time_zone => $self->{tz});

    if ($end !~ /(\d{4})-(\d{2})-(\d{2})/) {
        $self->{logger}->writeLog('ERROR', "Verify period end format");
    }
    my $dt2 = DateTime->new(year => $1, month => $2, day => $3, hour => 0, minute => 0, second => 0, time_zone => $self->{tz});

    my $epoch = $dt1->epoch();
    my $epoch_end = $dt2->epoch();
    if ($epoch_end <= $epoch) {
        $self->{logger}->writeLog('ERROR', "Period end date is older");
    }

    my $partitions = [];
    while ($epoch < $epoch_end) {
        $dt1->add(days => 1);

        $epoch = $dt1->epoch();
        my $month = $dt1->month();
        $month = '0' . $month if ($month < 10);
        my $day = $dt1->day();
        $day = '0' . $day if ($day < 10);

        push @$partitions, {
            name => $dt1->year() . $month . $day,
            date => $dt1->year() . '-' . $month . '-' . $day,
            epoch => $epoch
        };
    }

    return $partitions;
}

sub checkDateFormat {
	my ($self, $start, $end) = @_;

	if (defined($start) && $start =~ /[1-2][0-9]{3}\-[0-1][0-9]\-[0-3][0-9]/
        && defined($end) && $end =~ /[1-2][0-9]{3}\-[0-1][0-9]\-[0-3][0-9]/) {
        return 1;
	}
	return 0;
}

sub getRebuildPeriods {
	my ($self, $start, $end) = @_;
	
	my ($day,$month,$year) = (localtime($start))[3,4,5];
	$start = POSIX::mktime(0,0,0,$day,$month,$year,0,0,-1);
	my $previousDay = POSIX::mktime(0,0,0,$day - 1,$month,$year,0,0,-1);
	my @days = ();
	while ($start < $end) {
	    # if there is few hour gap (time change : winter/summer), we also readjust it
		if ($start == $previousDay) {
		    $start = POSIX::mktime(0,0,0, ++$day, $month, $year,0,0,-1);
		}
		my $dayEnd = POSIX::mktime(0, 0, 0, ++$day, $month, $year, 0, 0, -1);
		
		my %period = ("start" => $start, "end" => $dayEnd);
		$days[scalar(@days)] = \%period;
		$previousDay = $start;
		$start = $dayEnd;
    }
    return (\@days);
}

#parseFlatFile (file, key,value) : replace a line with a  key by a value (entire line) to the specified file
sub parseAndReplaceFlatFile{
	my $self = shift;
    my $file = shift;
    my $key = shift;
    my $value = shift;
    
 	if (!-e $file) {
 		$self->{logger}->writeLog('ERROR', "File missing [".$file."]. Make sure you installed all the pre-requisites before executing this script");
 	} 
   
    tie my @flatfile, 'Tie::File', $file or die $!;
	
	foreach my $line(@flatfile)
    {
		if( $line =~ m/$key/ ) {
			my $previousLine = $line;
			$line =~ s/$key/$value/g;
			$self->{logger}->writeLog('DEBUG', "[".$file."]");
			$self->{logger}->writeLog('DEBUG', "Replacing [".$previousLine."] by [".$value."]");
		}
    }
}

1;
