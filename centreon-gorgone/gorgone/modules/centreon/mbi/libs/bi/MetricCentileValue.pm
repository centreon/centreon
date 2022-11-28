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

package gorgone::modules::centreon::mbi::libs::bi::MetricCentileValue;

# Constructor
# parameters:
# $logger: instance of class CentreonLogger
# $centstorage: Instance of centreonDB class for connection to Centreon database
# $centreon: Instance of centreonDB class for connection to Centstorage database
sub new {
    my ($class, %options) = (shift, @_);
    my $self  = {};
    $self->{logger} = $options{logger};
    $self->{centstorage} = $options{centstorage};
    $self->{centreon}  = $options{centreon};
    $self->{time}  = $options{time};
    $self->{centileProperties} = $options{centileProperties};
    $self->{timePeriod}  = $options{timePeriod};
    $self->{liveService}  = $options{liveService};
    
    $self->{today_servicemetrics} = "mod_bi_tmp_today_servicemetrics"; #BIMetric -> createTodayTable
    
    #Daily values
    $self->{name} = "mod_bi_metriccentiledailyvalue";
    
    #Week values 
    $self->{name_week} = "mod_bi_metriccentileweeklyvalue";
    
    #Month values
    $self->{name_month} = "mod_bi_metriccentilemonthlyvalue";
    
    $self->{timeColumn} = "time_id";
    bless $self, $class;
    return $self;
}

#getName($granularity) : "month","week" 
sub getName {
    my $self = shift;
    my $granularity = shift;
    my $name = $self->{name};
    
    if (defined($granularity) && ($granularity eq "month" || $granularity eq "week")) {
        my $key = 'name_' . $granularity;
        $name = $self->{$key};
    }
    return $name;
}

sub getTmpName {
    my ($self, $granularity) = @_;
    my $name = $self->{tmp_name};
    if (defined $granularity && ($granularity eq "month" || $granularity eq "week")) {
        my $key = 'tmp_name_' . $granularity;
        $name = $self->{$key};
    }
    
    return $name;
}

sub getTimeColumn {
    my $self = shift;

    return $self->{timeColumn};
}

sub getMetricsCentile {
    my ($self, %options) = @_;
    
    my $results = {};
    my $centileServiceCategories = $options{etlProperties}->{'centile.include.servicecategories'};
    my $query = 'SELECT id, metric_id FROM ' . $self->{today_servicemetrics} . ' sm ' .
        ' WHERE sm.sc_id IN (' . $centileServiceCategories . ')';
    my $sth = $self->{centstorage}->query($query);
    while (my $row = $sth->fetchrow_arrayref()) {
        $results->{$$row[1]} = [] if (!defined($results->{$$row[1]}));
        push @{$results->{$$row[1]}}, $$row[0];
    }

    return $results;
}

sub getTimePeriodQuery {
    my ($self, %options) = @_;
    
    my $subQuery = '';
    # Get the time period to apply to each days of the period given in parameter 
    my $totalDays = $self->{time}->getTotalDaysInPeriod($options{start}, $options{end}) + 1; # +1 because geTotalDaysInPeriod return the number of day between start 00:00 and end 00:00
    my $counter = 1;
    my $currentStart = $options{start};
    my $append = '';
    while ($counter <= $totalDays) {
        my $rangeDay = $self->{timePeriod}->getTimeRangesForDayByDateTime($options{liveServiceName}, $currentStart, $self->{time}->getDayOfWeek($currentStart));
        if (scalar($rangeDay)) {
            my @tabPeriod = @$rangeDay;
            my ($start_date, $end_date);
            my $tabSize = scalar(@tabPeriod);
            for (my $count = 0; $count < $tabSize; $count++)  {
                my $range = $tabPeriod[$count];
                if ($count == 0) {
                    $start_date = $range->[0];
                }
                if ($count == $tabSize - 1) {
                    $end_date = $range->[1];
                }
                $subQuery .= $append . "(ctime >= UNIX_TIMESTAMP(" . ($range->[0]) . ") AND ctime < UNIX_TIMESTAMP(" . ($range->[1]) . "))";
                $append = ' OR ';
            }
        }
        $currentStart = $self->{time}->addDateInterval($currentStart, 1, "DAY");
        $counter++;
    }
    
    return $subQuery;
}

sub calcMetricsCentileValueMultipleDays {
    my ($self, %options) = @_;
    
    my $centileParam = $self->{centileProperties}->getCentileParams();
    foreach (@$centileParam) {
        my ($centile, $timeperiodId) = ($_->{centile_param}, $_->{timeperiod_id});
        my ($liveServiceName, $liveServiceId) = $self->{liveService}->getLiveServicesByNameForTpId($timeperiodId);
        
        #Get Id for the couple centile / timeperiod
        my $centileId;
        my $query = "SELECT id FROM mod_bi_centiles WHERE centile_param = " . $centile . " AND liveservice_id = (SELECT id FROM mod_bi_liveservice WHERE timeperiod_id  = " . $timeperiodId . ")";
        my $sth = $self->{centstorage}->query($query);
        while (my $row = $sth->fetchrow_hashref()) {
            if (defined($row->{id})) {
                $centileId = $row->{id};
            }
        }
        
        next if (!defined($centileId));
        
        my $total = scalar(keys %{$options{metricsId}});
        $self->{logger}->writeLog("INFO", "Processing " . $options{granularity} . " for Centile: [" . $options{start} . "] to [" . $options{end} . "] - " . $liveServiceName . " - " . $centile . ' (' . $total . ' metrics)');
        my $sub_query_timeperiod = $self->getTimePeriodQuery(start => $options{start}, end => $options{end}, liveServiceName => $liveServiceName);
        $query = 'SELECT value FROM (SELECT value, @counter := @counter + 1 AS counter FROM (select @counter := 0) AS initvar, data_bin WHERE id_metric = ? AND (' . $sub_query_timeperiod . ') ORDER BY value ASC) AS X where counter = ceil(' . $centile . ' * @counter / 100)';
        my $sth_centile = $self->{centstorage}->prepare($query);
        my $current = 1;
        foreach my $metricId (keys %{$options{metricsId}}) {
            $self->{logger}->writeLog("DEBUG", "Processing metric id for Centile: " . $metricId . " ($current/$total)");
            $sth_centile->execute($metricId);
            my $row = $sth_centile->fetchrow_arrayref();
            $current++;
            next if (!defined($row));

            foreach (@{$options{metricsId}->{$metricId}}) {
                my $query_insert = 'INSERT INTO ' . $self->getName($options{granularity}) . 
                        '(servicemetric_id, time_id, liveservice_id, centile_value, centile_param, centile_id, total, warning_treshold, critical_treshold)' .
                        "SELECT '" . $_ . "', '" . $options{timeId} . "', '" . $liveServiceId . "', '" . $$row[0] . "', '" . $centile . "', '" . $centileId . "', " . 
                        'm.max, m.warn, m.crit FROM metrics m WHERE m.metric_id = ' . $metricId;
                $self->{centstorage}->query($query_insert);
            }
        }
    }
}

1;
