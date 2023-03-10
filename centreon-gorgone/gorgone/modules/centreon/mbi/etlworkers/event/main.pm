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

package gorgone::modules::centreon::mbi::etlworkers::event::main;

use strict;
use warnings;
use gorgone::modules::centreon::mbi::libs::centreon::Timeperiod;
use gorgone::modules::centreon::mbi::libs::bi::HostAvailability;
use gorgone::modules::centreon::mbi::libs::bi::ServiceAvailability;
use gorgone::modules::centreon::mbi::libs::bi::HGMonthAvailability;
use gorgone::modules::centreon::mbi::libs::bi::HGServiceMonthAvailability;
use gorgone::modules::centreon::mbi::libs::bi::Time;
use gorgone::modules::centreon::mbi::libs::bi::MySQLTables;
use gorgone::modules::centreon::mbi::libs::bi::BIHostStateEvents;
use gorgone::modules::centreon::mbi::libs::bi::BIServiceStateEvents;
use gorgone::modules::centreon::mbi::libs::bi::LiveService;
use gorgone::modules::centreon::mbi::libs::centstorage::HostStateEvents;
use gorgone::modules::centreon::mbi::libs::centstorage::ServiceStateEvents;
use gorgone::modules::centreon::mbi::libs::Utils;
use gorgone::standard::misc;

my ($utils, $time, $tablesManager, $timePeriod);
my ($hostAv, $serviceAv);
my ($hgAv, $hgServiceAv);
my ($biHostEvents, $biServiceEvents);
my ($hostEvents, $serviceEvents);
my ($liveService);

sub initVars {
    my ($etlwk, %options) = @_;

    $utils = gorgone::modules::centreon::mbi::libs::Utils->new($etlwk->{messages});
    $timePeriod = gorgone::modules::centreon::mbi::libs::centreon::Timeperiod->new($etlwk->{messages}, $etlwk->{dbbi_centreon_con});
    $time = gorgone::modules::centreon::mbi::libs::bi::Time->new($etlwk->{messages}, $etlwk->{dbbi_centstorage_con});
    $tablesManager = gorgone::modules::centreon::mbi::libs::bi::MySQLTables->new($etlwk->{messages}, $etlwk->{dbbi_centstorage_con});
    $biHostEvents = gorgone::modules::centreon::mbi::libs::bi::BIHostStateEvents->new($etlwk->{messages}, $etlwk->{dbbi_centstorage_con}, $timePeriod);
	$biServiceEvents = gorgone::modules::centreon::mbi::libs::bi::BIServiceStateEvents->new($etlwk->{messages}, $etlwk->{dbbi_centstorage_con}, $timePeriod);
    $liveService = gorgone::modules::centreon::mbi::libs::bi::LiveService->new($etlwk->{messages}, $etlwk->{dbbi_centstorage_con});
    $hostEvents = gorgone::modules::centreon::mbi::libs::centstorage::HostStateEvents->new($etlwk->{messages}, $etlwk->{dbbi_centstorage_con}, $biHostEvents, $timePeriod);
	$serviceEvents = gorgone::modules::centreon::mbi::libs::centstorage::ServiceStateEvents->new($etlwk->{messages}, $etlwk->{dbbi_centstorage_con}, $biServiceEvents, $timePeriod);
    $hostAv = gorgone::modules::centreon::mbi::libs::bi::HostAvailability->new($etlwk->{messages}, $etlwk->{dbbi_centstorage_con});
	$serviceAv = gorgone::modules::centreon::mbi::libs::bi::ServiceAvailability->new($etlwk->{messages}, $etlwk->{dbbi_centstorage_con});
    $hgAv = gorgone::modules::centreon::mbi::libs::bi::HGMonthAvailability->new($etlwk->{messages}, $etlwk->{dbbi_centstorage_con});
	$hgServiceAv = gorgone::modules::centreon::mbi::libs::bi::HGServiceMonthAvailability->new($etlwk->{messages}, $etlwk->{dbbi_centstorage_con});
}

sub sql {
    my ($etlwk, %options) = @_;

    return if (!defined($options{params}->{sql}));

    foreach (@{$options{params}->{sql}}) {
        $etlwk->{messages}->writeLog('INFO', $_->[0]);
        if ($options{params}->{db} eq 'centstorage') {
            $etlwk->{dbbi_centstorage_con}->query({ query => $_->[1] });
        } elsif ($options{params}->{db} eq 'centreon') {
            $etlwk->{dbbi_centreon_con}->query({ query => $_->[1] });
        }
    }
}

sub processEventsHosts {
    my ($etlwk, %options) = @_;

    my $mode = 'daily';
    if ($options{options}->{rebuild} == 1) {
        $tablesManager->emptyTableForRebuild($biHostEvents->getName(), $tablesManager->dumpTableStructure($biHostEvents->getName()), $biHostEvents->getTimeColumn());
        $mode = 'rebuild';
    } else {
        $biHostEvents->deleteUnfinishedEvents();
    }

    if ($options{options}->{rebuild} == 1) {
		$tablesManager->dropIndexesFromReportingTable('mod_bi_hoststateevents');
	}

    #Agreggate events by TP and store them into a temporary table (mod_bi_hoststateevents_tmp)
	$etlwk->{messages}->writeLog("INFO", "[HOST] Processing host events");
	$hostEvents->agreggateEventsByTimePeriod(
        $options{etlProperties}->{'liveservices.availability'},
        $options{start},
        $options{end},
        $options{liveServices},
        $mode
    );

	#Dump the result of aggregated data join to dimensions and load this to the final mod_bi_hoststateevents table
	my $request = "INSERT INTO mod_bi_hoststateevents ";
    $request .= " SELECT id, t1.modbiliveservice_id, t1.state, t1.start_time, t1.end_time, t1.duration, t1.sla_duration,";
	$request .= " t1.ack_time, t1.last_update from mod_bi_hoststateevents_tmp t1";
	$request .= " INNER JOIN mod_bi_tmp_today_hosts t2 on t1.host_id = t2.host_id";

    $etlwk->{messages}->writeLog("INFO", "[HOST] Loading calculated events in reporting table");
    $etlwk->{dbbi_centstorage_con}->query({ query => $request });
	
	if ($options{options}->{rebuild} == 1 && $options{options}->{rebuild} == 0) {
		$etlwk->{messages}->writeLog("DEBUG", "[HOST] Creating index");
		$etlwk->{dbbi_centstorage_con}->query({ query => 'ALTER TABLE mod_bi_hoststateevents ADD INDEX `modbihost_id` (`modbihost_id`,`modbiliveservice_id`,`state`,`start_time`,`end_time`)' });
		$etlwk->{dbbi_centstorage_con}->query({ query => 'ALTER TABLE mod_bi_hoststateevents ADD INDEX `state` (`state`,`modbiliveservice_id`,`start_time`,`end_time`)' });
		$etlwk->{dbbi_centstorage_con}->query({ query => 'ALTER TABLE mod_bi_hoststateevents ADD INDEX `idx_mod_bi_hoststateevents_end_time` (`end_time`)' });
	}
}

sub processEventsServices {
    my ($etlwk, %options) = @_;

    my $mode = 'daily';
    if ($options{options}->{rebuild} == 1) {
        $tablesManager->emptyTableForRebuild($biServiceEvents->getName(), $tablesManager->dumpTableStructure($biServiceEvents->getName()), $biServiceEvents->getTimeColumn());
        $mode = 'rebuild';
    } else {
        $biServiceEvents->deleteUnfinishedEvents();
    }

    if ($options{options}->{rebuild} == 1) {
		$tablesManager->dropIndexesFromReportingTable('mod_bi_servicestateevents');
	}

    #Agreggate events by TP and store them into a temporary table (mod_bi_hoststateevents_tmp)
	$etlwk->{messages}->writeLog("INFO", "[SERVICE] Processing service events");
	$serviceEvents->agreggateEventsByTimePeriod(
        $options{etlProperties}->{'liveservices.availability'},
        $options{start},
        $options{end},
        $options{liveServices},
        $mode
    );

	#Dump the result of aggregated data join to dimensions and load this to the final mod_bi_hoststateevents table
	my $request = "INSERT INTO mod_bi_servicestateevents ";
    $request .= " SELECT id,t1.modbiliveservice_id,t1.state,t1.start_time,t1.end_time,t1.duration,t1.sla_duration,";
	$request .= " t1.ack_time,t1.last_update FROM mod_bi_servicestateevents_tmp t1 INNER JOIN mod_bi_tmp_today_services t2 ";
	$request .= " ON t1.host_id = t2.host_id AND t1.service_id = t2.service_id";

    $etlwk->{messages}->writeLog("INFO", "[SERVICE] Loading calculated events in reporting table");
    $etlwk->{dbbi_centstorage_con}->query({ query => $request });

	if ($options{options}->{rebuild} == 1 && $options{options}->{rebuild} == 0) {
		$etlwk->{messages}->writeLog("DEBUG", "[SERVICE] Creating index");
        $etlwk->{dbbi_centstorage_con}->query({ query => 'ALTER TABLE mod_bi_servicestateevents ADD INDEX `modbiservice_id` (`modbiservice_id`,`modbiliveservice_id`,`state`,`start_time`,`end_time`)' });
		$etlwk->{dbbi_centstorage_con}->query({ query => 'ALTER TABLE mod_bi_servicestateevents ADD INDEX `state` (`state`,`modbiliveservice_id`,`start_time`,`end_time`)' });
		$etlwk->{dbbi_centstorage_con}->query({ query => 'ALTER TABLE mod_bi_servicestateevents ADD INDEX `idx_mod_bi_servicestateevents_end_time` (`end_time`)' });
	}
}

sub events {
    my ($etlwk, %options) = @_;

    initVars($etlwk, %options);

    my ($startTimeId, $startUtime) = $time->getEntryID($options{params}->{start});
    my ($endTimeId, $endUtime) = $time->getEntryID($options{params}->{end});

    my $liveServices = $liveService->getLiveServicesByTpId();

    if (defined($options{params}->{hosts}) && $options{params}->{hosts} == 1) {
        processEventsHosts($etlwk, start => $startUtime, end => $endUtime, liveServices => $liveServices, %options);
    } elsif (defined($options{params}->{services}) && $options{params}->{services} == 1) {
        processEventsServices($etlwk, start => $startUtime, end => $endUtime, liveServices => $liveServices, %options);
    }
}

sub availabilityDayHosts {
    my ($etlwk, %options) = @_;

    $etlwk->{messages}->writeLog("INFO", "[AVAILABILITY] Processing hosts day: $options{params}->{start} => $options{params}->{end} [$options{params}->{liveserviceName}]");
    my $ranges = $timePeriod->getTimeRangesForDay($options{startWeekDay}, $options{params}->{liveserviceName}, $options{startUtime});
    my $dayEvents = $biHostEvents->getDayEvents($options{startUtime}, $options{endUtime}, $options{params}->{liveserviceId}, $ranges);
    $hostAv->insertStats($dayEvents, $options{startTimeId}, $options{params}->{liveserviceId});
}

sub availabilityDayServices {
    my ($etlwk, %options) = @_;

    $etlwk->{messages}->writeLog("INFO", "[AVAILABILITY] Processing services day: $options{params}->{start} => $options{params}->{end} [$options{params}->{liveserviceName}]");
    my $ranges = $timePeriod->getTimeRangesForDay($options{startWeekDay}, $options{params}->{liveserviceName}, $options{startUtime});
    my $dayEvents = $biServiceEvents->getDayEvents($options{startUtime}, $options{endUtime}, $options{params}->{liveserviceId}, $ranges);
    $serviceAv->insertStats($dayEvents, $options{startTimeId}, $options{params}->{liveserviceId});
}

sub availabilityMonthHosts {
    my ($etlwk, %options) = @_;

    $etlwk->{messages}->writeLog("INFO", "[AVAILABILITY] Processing services month: $options{params}->{start} => $options{params}->{end}");
    my $data = $hostAv->getHGMonthAvailability($options{params}->{start}, $options{params}->{end}, $biHostEvents);
    $hgAv->insertStats($options{startTimeId}, $data);
}

sub availabilityMonthServices {
    my ($etlwk, %options) = @_;

    $etlwk->{messages}->writeLog("INFO", "[AVAILABILITY] Processing hosts month: $options{params}->{start} => $options{params}->{end}");
    my $data = $serviceAv->getHGMonthAvailability_optimised($options{params}->{start}, $options{params}->{end}, $biServiceEvents);
    $hgServiceAv->insertStats($options{startTimeId}, $data);
}

sub availability {
    my ($etlwk, %options) = @_;

    initVars($etlwk, %options);

    my ($startTimeId, $startUtime) = $time->getEntryID($options{params}->{start});
    my ($endTimeId, $endUtime) = $time->getEntryID($options{params}->{end});
    my $startWeekDay = $utils->getDayOfWeek($options{params}->{start});

    if ($options{params}->{type} eq 'availability_day_hosts') {
        availabilityDayHosts(
            $etlwk,
            startTimeId => $startTimeId,
            startUtime => $startUtime,
            endTimeId => $endTimeId,
            endUtime => $endUtime,
            startWeekDay => $startWeekDay,
            %options
        );
    } elsif ($options{params}->{type} eq 'availability_day_services') {
        availabilityDayServices(
            $etlwk,
            startTimeId => $startTimeId,
            startUtime => $startUtime,
            endTimeId => $endTimeId,
            endUtime => $endUtime,
            startWeekDay => $startWeekDay,
            %options
        );
    } elsif ($options{params}->{type} eq 'availability_month_services') {
         availabilityMonthServices(
            $etlwk,
            startTimeId => $startTimeId,
            %options
         );
    } elsif ($options{params}->{type} eq 'availability_month_hosts') {
        availabilityMonthHosts(
            $etlwk,
            startTimeId => $startTimeId,
            %options
        );
    }
}

1;
