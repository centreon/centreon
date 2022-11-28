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

package gorgone::modules::centreon::mbi::libs::centreon::ETLProperties;

# Constructor
# parameters:
# $logger: instance of class CentreonLogger
# $centreon: Instance of centreonDB class for connection to Centreon database
# $centstorage: (optionnal) Instance of centreonDB class for connection to Centstorage database
sub new {
    my $class = shift;
    my $self  = {};

    $self->{logger}	= shift;
    $self->{centreon} = shift;
    if (@_) {
        $self->{centstorage}  = shift;
    }
    bless $self, $class;
    return $self;
}

# returns two references to two hash tables => hosts indexed by id and hosts indexed by name
sub getProperties {
    my $self = shift;

    my $activated = 1;
    if (@_) {
        $activated  = 0;
    }
    my (%etlProperties, %dataRetention);

    my $query = "SELECT `opt_key`, `opt_value` FROM `mod_bi_options` WHERE `opt_key` like 'etl.%'";
    my $sth = $self->{centreon}->query($query);
    while (my $row = $sth->fetchrow_hashref()) {
        if ($row->{opt_key} =~ /etl.retention.(.*)/) {
            $dataRetention{$1} = $row->{opt_value};
        } elsif ($row->{opt_key} =~ /etl.list.(.*)/) {
            my @tab = split (/,/, $row->{opt_value});
            my %hashtab = ();
            foreach(@tab) {
                $hashtab{$_} = 1;
            }
            $etlProperties{$1} = \%hashtab;
        } elsif ($row->{opt_key} =~ /etl.(.*)/) {
            $etlProperties{$1} = $row->{opt_value};
        }
    }
    if (defined($etlProperties{'capacity.exclude.metrics'})) {
        $etlProperties{'capacity.exclude.metrics'} =~ s/^/\'/;
        $etlProperties{'capacity.exclude.metrics'} =~ s/$/\'/;
        $etlProperties{'capacity.exclude.metrics'} =~ s/,/\',\'/;
    }

    return (\%etlProperties, \%dataRetention);
}

# returns the max retention period defined by type of statistics, monthly stats are excluded
sub getMaxRetentionPeriodFor {
    my $self = shift;
    my $logger = $self->{'logger'};

    my $type = shift;
    my $query = "SELECT date_format(NOW(), '%Y-%m-%d') as period_end,";
    $query .= "  date_format(DATE_ADD(NOW(), INTERVAL MAX(CAST(`opt_value` as SIGNED INTEGER))*-1 DAY), '%Y-%m-%d') as period_start";
    $query .= " FROM `mod_bi_options` ";
    $query .= " WHERE `opt_key` IN ('etl.retention.".$type.".hourly','etl.retention.".$type.".daily', 'etl.retention.".$type.".raw')";
    my $sth = $self->{centreon}->query($query);

    if (my $row = $sth->fetchrow_hashref()) {
        return ($row->{period_start}, $row->{period_end});
    }

    die 'Cannot get max perfdata retention period. Verify your data retention options';
}

# Returns a start and a end date for each retention period
sub getRetentionPeriods {
    my $self = shift;
    my $logger = $self->{'logger'};

    my $query = "SELECT date_format(NOW(), '%Y-%m-%d') as period_end,";
    $query .= "  date_format(DATE_ADD(NOW(), INTERVAL (`opt_value`)*-1 DAY), '%Y-%m-%d') as period_start,";
    $query .= " opt_key ";
    $query .= " FROM `mod_bi_options` ";
    $query .= " WHERE `opt_key` like ('etl.retention.%')";
    my $sth = $self->{centreon}->query($query);
    my %periods = ();
    while (my $row = $sth->fetchrow_hashref()) {
        $row->{'opt_key'} =~ s/etl.retention.//; 
        $periods{$row->{'opt_key'}} = { start => $row->{period_start}, end => $row->{period_end}} ;
    }
    if (!scalar(keys %periods)){
        $logger->writeLog("FATAL", "Cannot retention periods information. Verify your data retention options");
    }
    return (\%periods);
}
1;
