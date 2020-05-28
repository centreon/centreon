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
use gorgone::standard::constants qw(:all);

=pod
cluster status:
UNKNOWN_STATUS: module restart when failoverProgress or failbackProgress running
NOTREADY_STATUS: init phase or sqlite issue at beginning
READY_STATUS: cluster can migrate
FAILOVER_RUNNING_STATUS
FAILOVER_FAIL_STATUS
FAILOVER_SUCCESS_STATUS
FAILBACK_RUNNING_STATUS
FAILBACK_FAIL_STATUS
FAILBACK_SUCCESS_STATUS

migrate step:
1) update gorgone sqlite status = FAILOVER_RUNNING_STATUS (state = STATE_MIGRATION_UPDATE_SQLITE)
2) change centreon DB poller configuration (state = STATE_MIGRATION_UPDATE_CENTREON_DB)
3) generate config files for 2 configuration (listener on 2 clapi commands) (state = STATE_MIGRATION_GENERATE_CONFIGS)
4) push config/reload poller failed (listener on a pipeline) (state = STATE_MIGRATION_POLLER_FAILED) (continue even if it's failed)
5) push config/reload poller spare (listener on a pipeline) (state = STATE_MIGRATION_POLLER_SPARE)
6) update 'running' poller failed in centreon DB (state = STATE_MIGRATION_UPDATE_RUNNING_POLLER_FAILED)

timeout on each step of a pipeline (default: 600 seconds) (finish and get an error if we have a listener on global pipeline token)
timeout on listener (default: 600 seconds). Need to set a listener value higher than each steps

=cut

use constant UNKNOWN_STATUS => -2;
use constant NOTREADY_STATUS => -1;
use constant READY_STATUS => 0;
use constant FAILOVER_RUNNING_STATUS => 1;
use constant FAILOVER_FAIL_STATUS => 2;
use constant FAILOVER_SUCCESS_STATUS => 3;
use constant FAILBACK_RUNNING_STATUS => 10;
use constant FAILBACK_FAIL_STATUS => 11;
use constant FAILBACK_SUCCESS_STATUS => 12;

use constant STATE_MIGRATION_UPDATE_SQLITE => 1;
use constant STATE_MIGRATION_UPDATE_CENTREON_DB => 2;
use constant STATE_MIGRATION_GENERATE_CONFIGS => 3;
use constant STATE_MIGRATION_POLLER_FAILED => 4;
use constant STATE_MIGRATION_POLLER_SPARE => 5;
use constant STATE_MIGRATION_UPDATE_RUNNING_POLLER_FAILED => 6;

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

    $config->{alive_timeout} = defined($config->{alive_timeout}) && $config->{alive_timeout} =~ /(\d+)/ ? $1 : 600;
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

    my $current_time = time();
    foreach (keys %{$options{clusters}}) {
        next if ($options{clusters}->{$_}->{status} != READY_STATUS);

        # migrate if: node is down (running) and spare is ok (running and last update recent)
        if (!defined($options{module}->{nodes}->{ $options{clusters}->{$_}->{spare} }->{running}) || 
            $options{module}->{nodes}->{ $options{clusters}->{$_}->{spare} }->{running} == 0 ||
            ($current_time - $options{clusters}->{$_}->{alive_timeout}) > $options{module}->{nodes}->{ $options{clusters}->{$_}->{spare} }->{last_alive}
        ) {
            $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{$_}->{name} . "' cannot migrate - spare poller not alive");
            next;
        }

        my $node_src;
        foreach my $node_id (keys %{$options{module}->{nodes}}) {
            if (defined($options{module}->{nodes}->{$node_id}->{running}) && $options{module}->{nodes}->{$node_id}->{running} == 1 &&
                (($current_time - $options{clusters}->{$_}->{alive_timeout}) > $options{module}->{nodes}->{$node_id}->{last_alive})
            ) {
                $node_src = $node_id;
                last;
            }
        }

        if (defined($node_src)) {
            migrate_steps_1_2_3(
                module => $options{module},
                node_src => $node_src,
                clusters => $options{clusters},
                cluster => $_
            );
        }
    }
}

sub migrate_steps_1_2_3 {
    my (%options) = @_;

    if ($options{module}->get_clapi_user() != 0) {
        return -1;
    }

    my ($status, $datas) = $options{module}->{class_object_centreon}->custom_execute(
        request => 'SELECT host_host_id ' .
            'FROM ns_host_relation ' .
            'WHERE nagios_server_id = ' . $options{module}->{class_object_centreon}->quote($options{node_src}),
        mode => 2
    );
    if ($status == -1) {
        $options{module}->{logger}->writeLogError("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' cannot get hosts associated --> poller $options{node_src}");
        return -1;
    }
    if (scalar(@$datas) <= 0) {
        $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' no hosts associated --> poller $options{node_src}");
        return 0;
    }

    $options{clusters}->{ $options{cluster} }->{status} = FAILOVER_RUNNING_STATUS;
    $options{clusters}->{ $options{cluster} }->{token_config_node_failed} = undef;
    $options{clusters}->{ $options{cluster} }->{token_config_node_spare} = undef;
    $options{clusters}->{ $options{cluster} }->{token_config_responses} = 0;
    $options{clusters}->{ $options{cluster} }->{token_pipeline_node_failed} = undef;
    $options{clusters}->{ $options{cluster} }->{token_pipeline_node_spare} = undef;
    $options{clusters}->{ $options{cluster} }->{node_src} = $options{node_src};

    ########
    # Step 1
    ########
    $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_MIGRATION_UPDATE_SQLITE started");
    $options{clusters}->{ $options{cluster} }->{state} = STATE_MIGRATION_UPDATE_SQLITE;

    my $data = { node_src => $options{node_src}, hosts => $datas };
    ($status, my $encoded) = $options{module}->json_encode(
        argument => $data,
        method => "-class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_MIGRATION_UPDATE_SQLITE"
    );

    ($status) = $options{module}->{db_gorgone}->query(
        'UPDATE INTO gorgone_centreon_judge_spare SET `status` = ' . FAILOVER_RUNNING_STATUS . ', `data` = ' . $options{module}->{db_gorgone}->quote($encoded)
    );
    if ($status == -1) {
        $options{module}->{logger}->writeLogError("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_MIGRATION_UPDATE_SQLITE: cannot update sqlite");
        return -1;
    }

    $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_MIGRATION_UPDATE_SQLITE finished");
    ########
    # Step 2
    ########
    $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_MIGRATION_UPDATE_CENTREON_DB started");
    $options{clusters}->{ $options{cluster} }->{state} = STATE_MIGRATION_UPDATE_CENTREON_DB;
    ($status) = $options{module}->{class_object_centstorage}->custom_execute(
        request => 'UPDATE ns_host_relation SET nagios_server_id = ' . $options{module}->{class_object_centreon}->quote($options{clusters}->{ $options{cluster} }->{spare}) .
            ' WHERE host_host_id IN (' . join(',', $data->{hosts}) . ')'
    );
    if ($status == -1) {
        $options{module}->{logger}->writeLogError("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_MIGRATION_UPDATE_CENTREON_DB: cannot update database");
        return -1;
    }

    $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_MIGRATION_UPDATE_CENTREON_DB finished");
    ########
    # Step 3
    ########
    $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_MIGRATION_GENERATE_CONFIGS started");
    $options{clusters}->{ $options{cluster} }->{state} = STATE_MIGRATION_GENERATE_CONFIGS;
    $options{clusters}->{ $options{cluster} }->{token_config_node_failed} = 'judge-spare##' . $options{clusters}->{ $options{cluster} }->{name} . '##' . STATE_MIGRATION_GENERATE_CONFIGS . '##' . $options{module}->generate_token(length => 8); 
    $options{clusters}->{ $options{cluster} }->{token_config_node_spare} = 'judge-spare##' . $options{clusters}->{ $options{cluster} }->{name} . '##' . STATE_MIGRATION_GENERATE_CONFIGS . '##' . $options{module}->generate_token(length => 8); 

    $options{module}->send_internal_action(
        action => 'ADDLISTENER',
        data => [
            {
                identity => 'gorgonejudge',
                event => 'JUDGELISTENER',
                token => $options{clusters}->{ $options{cluster} }->{token_config_node_failed},
                timeout => 180
            }
        ]
    );
    $options{module}->send_internal_action(
        action => 'COMMAND',
        token => $options{clusters}->{ $options{cluster} }->{token_config_node_failed},
        data => {
            content => [
                {
                    instant => 1,
                    command => 'centreon -u ' . $options{module}->{clapi_user} . ' -p ' . $options{module}->{clapi_password} . ' -a POLLERGENERATE -v ' . $options{node_src},
                    timeout => 150
                }
            ]
        }
    );

    $options{module}->send_internal_action(
        action => 'ADDLISTENER',
        data => [
            {
                identity => 'gorgonejudge',
                event => 'JUDGELISTENER',
                token => $options{clusters}->{ $options{cluster} }->{token_config_node_spare},
                timeout => 180
            }
        ]
    );
    $options{module}->send_internal_action(
        action => 'COMMAND',
        token => $options{clusters}->{ $options{cluster} }->{token_config_node_spare},
        data => {
            content => [
                {
                    instant => 1,
                    command => 'centreon -u ' . $options{module}->{clapi_user} . ' -p ' . $options{module}->{clapi_password} . ' -a POLLERGENERATE -v ' . $options{clusters}->{ $options{cluster} }->{spare},
                    timeout => 150
                }
            ]
        }
    );

    return 0;
}

sub migrate_step_3 {
    my (%options) = @_;

    return 0 if ($options{code} != GORGONE_ACTION_FINISH_KO || $options{code} != GORGONE_ACTION_FINISH_OK);
    return 0 if ($options{token} ne $options{clusters}->{ $options{cluster} }->{token_config_node_failed} &&
        $options{token} ne $options{clusters}->{ $options{cluster} }->{token_config_node_spare});

    if ($options{code} == GORGONE_ACTION_FINISH_KO) {
        $options{module}->{logger}->writeLogError("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_MIGRATION_GENERATE_CONFIGS: generate config error");
        $options{clusters}->{ $options{cluster} }->{status} = FAILOVER_FAIL_STATUS;
        return -1;
    }
    
    $options{clusters}->{ $options{cluster} }->{token_config_responses}++;
    if ($options{clusters}->{ $options{cluster} }->{token_config_responses} < 2) {
        return 0;
    }

    $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_MIGRATION_GENERATE_CONFIGS finished");

    ########
    # Step 4
    ########
    $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step MIGRATION_POLLER_FAILED started");
    $options{clusters}->{ $options{cluster} }->{state} = STATE_MIGRATION_POLLER_FAILED;
    $options{clusters}->{ $options{cluster} }->{token_pipeline_node_failed} = 'judge-spare##' . $options{clusters}->{ $options{cluster} }->{name} . '##' . STATE_MIGRATION_POLLER_FAILED . '##' . $options{module}->generate_token(length => 8);

    $options{module}->send_internal_action(
        action => 'ADDLISTENER',
        data => [
            {
                identity => 'gorgonejudge',
                event => 'JUDGELISTENER',
                token => $options{clusters}->{ $options{cluster} }->{token_pipeline_node_failed},
                timeout => 450
            }
        ]
    );
    $options{module}->add_pipeline_config_reload_poller(
        token => $options{clusters}->{ $options{cluster} }->{token_pipeline_node_failed},
        poller_id => $options{clusters}->{ $options{cluster} }->{node_src},
        no_generate_config => 1,
        pipeline_timeout => 400
    );

    return 0;
}

sub migrate_step_4 {
    my (%options) = @_;

    return 0 if ($options{code} != GORGONE_ACTION_FINISH_KO || $options{code} != GORGONE_ACTION_FINISH_OK);

    $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step MIGRATION_POLLER_FAILED finished");

    ########
    # Step 5
    ########
    $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_MIGRATION_POLLER_SPARE started");
    $options{clusters}->{ $options{cluster} }->{state} = STATE_MIGRATION_POLLER_SPARE;
    $options{clusters}->{ $options{cluster} }->{token_pipeline_node_spare} = 'judge-spare##' . $options{clusters}->{ $options{cluster} }->{name} . '##' . STATE_MIGRATION_POLLER_SPARE . '##' . $options{module}->generate_token(length => 8); 

    $options{module}->send_internal_action(
        action => 'ADDLISTENER',
        data => [
            {
                identity => 'gorgonejudge',
                event => 'JUDGELISTENER',
                token => $options{clusters}->{ $options{cluster} }->{token_pipeline_node_spare},
                timeout => 450,
            }
        ]
    );
    $options{module}->add_pipeline_config_reload_poller(
        token => $options{clusters}->{ $options{cluster} }->{token_pipeline_node_spare},
        poller_id => $options{clusters}->{ $options{cluster} }->{spare},
        no_generate_config => 1,
        pipeline_timeout => 400
    );
}

sub migrate_step_5 {
    my (%options) = @_;

    return 0 if ($options{code} != GORGONE_ACTION_FINISH_KO || $options{code} != GORGONE_ACTION_FINISH_OK);

    if ($options{code} == GORGONE_ACTION_FINISH_KO) {
        $options{module}->{logger}->writeLogError("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_MIGRATION_POLLER_SPARE: pipeline error");
        $options{clusters}->{ $options{cluster} }->{status} = FAILOVER_FAIL_STATUS;
        return -1;
    }

    $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_MIGRATION_POLLER_SPARE finished");

    # TODO: update running

    $options{clusters}->{ $options{cluster} }->{status} = FAILOVER_SUCCESS_STATUS;

    return 0;
}

sub migrate_steps_listener_response {
    my (%options) = @_;

    return -1 if (!defined($options{clusters}->{ $options{cluster} }));
    if ($options{state} != $options{clusters}->{ $options{cluster} }->{state}) {
        $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' wrong or old step responce received");
        return -1;
    }

    if ($options{state} == STATE_MIGRATION_GENERATE_CONFIGS) {
        return migrate_step_3(%options);
    }
    if ($options{state} == STATE_MIGRATION_POLLER_FAILED) {
        return migrate_step_4(%options);
    }
    if ($options{state} == STATE_MIGRATION_POLLER_SPARE) {
        return migrate_step_5(%options);
    }
}

1;
