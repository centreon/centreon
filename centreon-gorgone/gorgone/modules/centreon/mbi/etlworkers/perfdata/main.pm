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

package gorgone::modules::centreon::mbi::etlworkers::perfdata::main;

use strict;
use warnings;

use gorgone::modules::centreon::mbi::libs::centreon::Timeperiod;
use gorgone::modules::centreon::mbi::libs::centreon::CentileProperties;
use gorgone::modules::centreon::mbi::libs::bi::LiveService;
use gorgone::modules::centreon::mbi::libs::bi::Time;
use gorgone::modules::centreon::mbi::libs::Utils;
use gorgone::modules::centreon::mbi::libs::centstorage::Metrics;
use gorgone::modules::centreon::mbi::libs::bi::MetricDailyValue;
use gorgone::modules::centreon::mbi::libs::bi::MetricHourlyValue;
use gorgone::modules::centreon::mbi::libs::bi::MetricCentileValue;
use gorgone::modules::centreon::mbi::libs::bi::MetricMonthCapacity;
use gorgone::standard::misc;

my ($utils, $time, $timePeriod, $centileProperties, $liveService);
my ($metrics);
my ($dayAgregates, $hourAgregates, $centileAgregates, $metricMonthCapacity);

sub initVars {
    my ($etlwk, %options) = @_;

    $timePeriod = gorgone::modules::centreon::mbi::libs::centreon::Timeperiod->new($etlwk->{messages}, $etlwk->{dbbi_centreon_con});
    $centileProperties = gorgone::modules::centreon::mbi::libs::centreon::CentileProperties->new($etlwk->{messages}, $etlwk->{dbbi_centreon_con});
    $liveService = gorgone::modules::centreon::mbi::libs::bi::LiveService->new($etlwk->{messages}, $etlwk->{dbbi_centstorage_con});
    $time = gorgone::modules::centreon::mbi::libs::bi::Time->new($etlwk->{messages}, $etlwk->{dbbi_centstorage_con});
    $utils = gorgone::modules::centreon::mbi::libs::Utils->new($etlwk->{messages});
    $metrics = gorgone::modules::centreon::mbi::libs::centstorage::Metrics->new($etlwk->{messages}, $etlwk->{dbbi_centstorage_con}, $options{pool_id});
    $dayAgregates = gorgone::modules::centreon::mbi::libs::bi::MetricDailyValue->new($etlwk->{messages}, $etlwk->{dbbi_centstorage_con}, $options{pool_id});
    $hourAgregates = gorgone::modules::centreon::mbi::libs::bi::MetricHourlyValue->new($etlwk->{messages}, $etlwk->{dbbi_centstorage_con}, $options{pool_id});
    $metricMonthCapacity = gorgone::modules::centreon::mbi::libs::bi::MetricMonthCapacity->new($etlwk->{messages}, $etlwk->{dbbi_centstorage_con});

    $centileAgregates = gorgone::modules::centreon::mbi::libs::bi::MetricCentileValue->new(
        logger => $etlwk->{messages},
        centstorage => $etlwk->{dbbi_centstorage_con},
        centreon => $etlwk->{dbbi_centreon_con},
        time => $time,
        centileProperties => $centileProperties,
        timePeriod => $timePeriod,
        liveService => $liveService
    );
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

sub perfdataDay {
    my ($etlwk, %options) = @_;

    my ($currentDayId, $currentDayUtime) = $time->getEntryID($options{params}->{start});
    my $ranges = $timePeriod->getTimeRangesForDayByDateTime(
        $options{params}->{liveserviceName},
        $options{params}->{start},
        $utils->getDayOfWeek($options{params}->{start})
    );
    if (scalar(@$ranges)) {
        $etlwk->{messages}->writeLog("INFO", "[PERFDATA] Processing day: $options{params}->{start} => $options{params}->{end} [$options{params}->{liveserviceName}]");
        $metrics->getMetricsValueByDay($ranges, $options{etlProperties}->{'tmp.storage.memory'});
        $dayAgregates->insertValues($options{params}->{liveserviceId}, $currentDayId);
    }    
}

sub perfdataMonth {
    my ($etlwk, %options) = @_;

    my ($previousMonthStartTimeId, $previousMonthStartUtime) = $time->getEntryID($options{params}->{start});
    my ($previousMonthEndTimeId, $previousMonthEndUtime) = $time->getEntryID($options{params}->{end});

    $etlwk->{messages}->writeLog("INFO", "[PERFDATA] Processing month: $options{params}->{start} => $options{params}->{end}");
    my $data = $dayAgregates->getMetricCapacityValuesOnPeriod($previousMonthStartTimeId, $previousMonthEndTimeId, $options{etlProperties});
    $metricMonthCapacity->insertStats($previousMonthStartTimeId, $data);
}

sub perfdataHour {
    my ($etlwk, %options) = @_;

    $etlwk->{messages}->writeLog("INFO", "[PERFDATA] Processing hours: $options{params}->{start} => $options{params}->{end}");

    $metrics->getMetricValueByHour($options{params}->{start}, $options{params}->{end}, $options{etlProperties}->{'tmp.storage.memory'});
    $hourAgregates->insertValues();
}

sub perfdata {
    my ($etlwk, %options) = @_;

    initVars($etlwk, %options);

    if ($options{params}->{type} eq 'perfdata_day') {
        perfdataDay($etlwk, %options);
    } elsif ($options{params}->{type} eq 'perfdata_month') {
        perfdataMonth($etlwk, %options);
    } elsif ($options{params}->{type} eq 'perfdata_hour') {
        perfdataHour($etlwk, %options);
    }
}

sub centileDay {
    my ($etlwk, %options) = @_;

    my ($currentDayId) = $time->getEntryID($options{params}->{start});

    my $metricsId = $centileAgregates->getMetricsCentile(etlProperties => $options{etlProperties});
    $centileAgregates->calcMetricsCentileValueMultipleDays(
        metricsId => $metricsId,
        start => $options{params}->{start},
        end => $options{params}->{end},
        granularity => 'day',
        timeId => $currentDayId
    );
}

sub centileMonth {
    my ($etlwk, %options) = @_;

    my ($previousMonthStartTimeId) = $time->getEntryID($options{params}->{start});

    my $metricsId = $centileAgregates->getMetricsCentile(etlProperties => $options{etlProperties});
    $centileAgregates->calcMetricsCentileValueMultipleDays(
        metricsId => $metricsId,
        start => $options{params}->{start},
        end => $options{params}->{end},
        granularity => 'month',
        timeId => $previousMonthStartTimeId
    );
}

sub centileWeek {
    my ($etlwk, %options) = @_;

    my ($currentDayId) = $time->getEntryID($options{params}->{start});

    my $metricsId = $centileAgregates->getMetricsCentile(etlProperties => $options{etlProperties});
    $centileAgregates->calcMetricsCentileValueMultipleDays(
        metricsId => $metricsId,
        start => $options{params}->{start},
        end => $options{params}->{end},
        granularity => 'week',
        timeId => $currentDayId
    );
}

sub centile {
    my ($etlwk, %options) = @_;

    initVars($etlwk, %options);

    if ($options{params}->{type} eq 'centile_day') {
        centileDay($etlwk, %options);
    } elsif ($options{params}->{type} eq 'centile_month') {
        centileMonth($etlwk, %options);
    } elsif ($options{params}->{type} eq 'centile_week') {
        centileWeek($etlwk, %options);
    }
}

1;
