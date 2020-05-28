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

package gorgone::modules::centreon::judge::type::spare;

use strict;
use warnings;

=pod
cluster status:
-2 = unknown (module restart when failoverProgress or failbackProgress running)
-1 = notReady (init phase or sqlite issue at beginning)
0  = ready (cluster can migrate)
1  = failoverProgress
2  = failbackProgress

migrate step:
- update gorgone sqlite status = failoverProgress (state = MIGRATE_UPDATE_SQLITE)
- change centreon DB poller configuration (state = MIGRATE_UPDATE_CENTREON_DB)
- generate config files for 2 configuration (listener on 2 clapi commands) (state = MIGRATION_GENERATE_CONFIGS)
- push config/reload poller failed (listener on a pipeline) (state = MIGRATION_POLLER_FAILED) (continue even if it's failed)
- push config/reload poller spare (listener on a pipeline) (state = MIGRATION_POLLER_SPARE)
- update 'running' poller failed in centreon DB (state = MIGRATION_UPDATE_RUNNING_POLLER_FAILED)

=cut

use constant UNKNOWN_STATUS => -2;
use constant NOTREADY_STATUS => -1;
use constant READY_STATUS => 0;
use constant FAILOVERPROGRESS_STATUS => 1;
use constant FAILBACKPROGRESS_STATUS => 2;

sub check_config {
    my (%options) = @_;

    my $config = $options{config};
    if (!defined($config->{nodes}) || scalar(@{$config->{nodes}}) <= 0) {
        $options{logger}->writeLogError("[judge] -class- please set nodes for cluster '" . $config->{name} . "'");
        return undef;
    }
    if (!defined($config->{spare})) {
        $options{logger}->writeLogError("[judge] -class- please set spare for cluster '" . $config->{name} . "'");
        return undef;
    }

    $config->{status} = NOTREADY_STATUS;

    return $config;
}

sub init {
    my (%options) = @_;

    foreach (keys %{$options{clusters}}) {
        next if ($options{clusters}->{$_}->{status} != NOTREADY_STATUS);

        my $query = 'SELECT `status` FROM gorgone_centreon_judge_spare WHERE cluster_name = ' . $options{module}->{db_gorgone}->quote($options{clusters}->{$_}->{name});
        my ($status, $sth) = $options{module}->{db_gorgone}->query($query);
        if ($status == -1) {
            $options{module}->{logger}->writeLogError("[judge] -class- sqlite error to get cluster information '" . $options{clusters}->{$_}->{name} . "': cannot select");
            next;
        }

        if (my $row = $sth->fetchrow_hashref()) {
            $options{clusters}->{$_}->{status} = $row->{status};
        } else {
            ($status) = $options{module}->{db_gorgone}->query(
                'INSERT INTO gorgone_centreon_judge_spare (`cluster_name`, `status`) VALUES (' . 
                    $options{module}->{db_gorgone}->quote($options{clusters}->{$_}->{name}) . ', ' . 
                    READY_STATUS . ')'
            );
            if ($status == -1) {
                $options{module}->{logger}->writeLogError("[judge] -class- sqlite error to get cluster information '" . $options{clusters}->{$_}->{name} . "': cannot insert");
                next;
            }
            $options{clusters}->{$_}->{status} = READY_STATUS;
        }

        $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{$_}->{name} . "' init status is " . $options{clusters}->{$_}->{status});
    }
}

sub check_migrate {
    my (%options) = @_;

    foreach (keys %{$options{clusters}}) {
        next if ($options{clusters}->{$_}->{status} != READY_STATUS);

        # migrate if: node is down and spare is ok (running and last update recent)
    }
}

1;
