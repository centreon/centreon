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

use constant STATE_FAILBACK_GET_SQLITE => 10;
use constant STATE_FAILBACK_UPDATE_CENTREON_DB => 11;
use constant STATE_FAILBACK_GENERATE_CONFIGS => 12;
use constant STATE_FAILBACK_POLLER_SRC => 13;
use constant STATE_FAILBACK_POLLER_DST => 14;

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
    $config->{live} = { status => NOTREADY_STATUS };

    return $config;
}

sub init {
    my (%options) = @_;

    foreach (keys %{$options{clusters}}) {
        next if ($options{clusters}->{$_}->{live}->{status} != NOTREADY_STATUS);

        my $query = 'SELECT `status` FROM gorgone_centreon_judge_spare WHERE cluster_name = ' . $options{module}->{db_gorgone}->quote($options{clusters}->{$_}->{name});
        my ($status, $sth) = $options{module}->{db_gorgone}->query($query);
        if ($status == -1) {
            $options{module}->{logger}->writeLogError("[judge] -class- sqlite error to get cluster information '" . $options{clusters}->{$_}->{name} . "': cannot select");
            next;
        }

        if (my $row = $sth->fetchrow_hashref()) {
            $options{clusters}->{$_}->{live}->{status} = $row->{status};
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
            $options{clusters}->{$_}->{live}->{status} = READY_STATUS;
        }

        $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{$_}->{name} . "' init status is " . $options{clusters}->{$_}->{live}->{status});
    }
}

sub send_log {
    my (%options) = @_;

    $options{module}->send_log(
        code => $options{code},
        token => defined($options{token}) ? $options{token} : $options{live}->{token},
        data => defined($options{data}) ? $options{data} : $options{live}
    );
}

sub is_ready_status {
    my (%options) = @_;

    if ($options{status} == READY_STATUS) {
        return 1;
    }

    return 0;
}

sub is_failover_status {
    my (%options) = @_;

    if ($options{status} == FAILOVER_FAIL_STATUS || $options{status} == FAILOVER_SUCCESS_STATUS) {
        return 1;
    }

    return 0;
}

sub is_spare_ready {
    my (%options) = @_;

    if (!defined($options{module}->{nodes}->{ $options{cluster}->{spare} }->{running}) || 
        $options{module}->{nodes}->{ $options{cluster}->{spare} }->{running} == 0 ||
        ($options{ctime} - $options{cluster}->{alive_timeout}) > $options{module}->{nodes}->{ $options{cluster}->{spare} }->{last_alive}
    ) {
        return 0;
    }

    return 1;
}

sub update_status {
    my (%options) = @_;

    my ($status) = $options{module}->{db_gorgone}->query(
        'UPDATE gorgone_centreon_judge_spare SET `status` = ' . $options{status} . ' ' .
        'WHERE `cluster_name` = ' . $options{module}->{db_gorgone}->quote($options{cluster})
    );
    if ($status == -1) {
        $options{module}->{logger}->writeLogError("[judge] -class- cluster '" . $options{cluster} . "' step $options{step}: cannot update status");
        return -1;
    }

    return 0;
}

sub check_migrate {
    my (%options) = @_;

    my $ctime = time();
    foreach (keys %{$options{clusters}}) {
        next if ($options{clusters}->{$_}->{live}->{status} != READY_STATUS);

        if (!is_spare_ready(module => $options{module}, cluster => $options{clusters}->{$_}, ctime => $ctime)) {
            $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{$_}->{name} . "' cannot migrate - spare poller not alive");
            next;
        }

        my $node_src;
        foreach my $node_id (@{$options{clusters}->{$_}->{nodes}}) {
            if (defined($options{module}->{nodes}->{$node_id}->{running}) && $options{module}->{nodes}->{$node_id}->{running} == 1 &&
                (($ctime - $options{clusters}->{$_}->{alive_timeout}) > $options{module}->{nodes}->{$node_id}->{last_alive})
            ) {
                $node_src = $node_id;
                last;
            }
        }

        if (defined($node_src)) {
            my $token = $options{module}->generate_token();
            send_log(
                module => $options{module},
                code => GORGONE_ACTION_BEGIN,
                token => $token,
                data => { message => 'failover start' }
            );
            migrate_steps_1_2_3(
                token => $options{token},
                module => $options{module},
                node_src => $node_src,
                clusters => $options{clusters},
                cluster => $_
            );
        }
    }
}

sub clean {
    my (%options) = @_;

    $options{clusters}->{ $options{cluster} }->{live}->{status} = READY_STATUS;
    if (update_status(
        module => $options{module},
        cluster => $options{cluster},
        status => READY_STATUS,
        step => 'clean'
        ) == -1) {
        send_log(
            module => $options{module},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => 'clean: cannot update status' }
        );
        $options{module}->{logger}->writeLogError("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' clean: cannot update status");
        return -1;
    }

    $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' clean: status updated");
    send_log(
        module => $options{module},
        code => GORGONE_ACTION_FINISH_OK,
        token => $options{token},
        data => { message => 'clean: status updated' }
    );
    return 0;
}

=pod

**********************
Failover migrate steps
**********************

=cut

sub migrate_steps_1_2_3 {
    my (%options) = @_;

    $options{clusters}->{ $options{cluster} }->{live}->{token} = $options{token};
    $options{clusters}->{ $options{cluster} }->{live}->{status} = FAILOVER_RUNNING_STATUS;
    $options{clusters}->{ $options{cluster} }->{live}->{token_config_node_failed} = undef;
    $options{clusters}->{ $options{cluster} }->{live}->{token_config_node_spare} = undef;
    $options{clusters}->{ $options{cluster} }->{live}->{token_config_responses} = 0;
    $options{clusters}->{ $options{cluster} }->{live}->{token_pipeline_node_failed} = undef;
    $options{clusters}->{ $options{cluster} }->{live}->{token_pipeline_node_spare} = undef;
    $options{clusters}->{ $options{cluster} }->{live}->{node_src} = $options{node_src};
    $options{clusters}->{ $options{cluster} }->{live}->{node_dst} = $options{clusters}->{ $options{cluster} }->{token_config_node_spare};
    $options{clusters}->{ $options{cluster} }->{live}->{no_update_running_failed} = $options{no_update_running_failed};
    $options{clusters}->{ $options{cluster} }->{live}->{state} = undef;

    send_log(module => $options{module}, code => GORGONE_MODULE_CENTREON_JUDGE_FAILOVER_RUNNING, live => $options{clusters}->{ $options{cluster} }->{live});

    if ($options{module}->get_clapi_user() != 0 ||
        $options{module}->get_pollers_config() != 0) {
        $options{clusters}->{ $options{cluster} }->{live}->{status} = READY_STATUS;
        send_log(
            module => $options{module},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{clusters}->{ $options{cluster} }->{live}->{token},
            data => { message => 'cannot get clapi user informations and/or poller config' }
        );
        return -1;
    }

    my ($status, $datas) = $options{module}->{class_object_centreon}->custom_execute(
        request => 'SELECT host_host_id ' .
            'FROM ns_host_relation ' .
            'WHERE nagios_server_id = ' . $options{module}->{class_object_centreon}->quote(value => $options{node_src}),
        mode => 2
    );
    if ($status == -1) {
        $options{module}->{logger}->writeLogError("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' cannot get hosts associated --> poller $options{node_src}");
        $options{clusters}->{ $options{cluster} }->{live}->{status} = READY_STATUS;
        send_log(
            module => $options{module},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{clusters}->{ $options{cluster} }->{live}->{token},
            data => { message => 'cannot get hosts associated with source poller' }
        );
        return -1;
    }
    if (scalar(@$datas) <= 0) {
        $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' no hosts associated --> poller $options{node_src}");
        $options{clusters}->{ $options{cluster} }->{live}->{status} = READY_STATUS;
        send_log(
            module => $options{module},
            code => GORGONE_ACTION_FINISH_OK,
            token => $options{clusters}->{ $options{cluster} }->{live}->{token},
            data => { message => 'nothing done - no hosts associated with source poller' }
        );
        return 0;
    }

    ########
    # Step 1
    ########
    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');
    $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_MIGRATION_UPDATE_SQLITE started");
    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');
    $options{clusters}->{ $options{cluster} }->{live}->{state} = STATE_MIGRATION_UPDATE_SQLITE;
    send_log(module => $options{module}, code => GORGONE_MODULE_CENTREON_JUDGE_FAILOVER_RUNNING, live => $options{clusters}->{ $options{cluster} }->{live});

    my $data = { node_src => $options{node_src}, hosts => [] };
    push @{$data->{hosts}}, $_->[0] foreach (@$datas);
    ($status, my $encoded) = $options{module}->json_encode(
        argument => $data,
        method => "-class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_MIGRATION_UPDATE_SQLITE"
    );
    if ($status == 1) {
        $options{module}->{logger}->writeLogError("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_MIGRATION_UPDATE_SQLITE: cannot encode json");
        send_log(
            module => $options{module},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{clusters}->{ $options{cluster} }->{live}->{token},
            data => { message => 'cannot encode json' }
        );
        return -1;
    }

    ($status) = $options{module}->{db_gorgone}->query(
        'UPDATE gorgone_centreon_judge_spare SET `status` = ' . FAILOVER_RUNNING_STATUS . ', `data` = ' . $options{module}->{db_gorgone}->quote($encoded) . ' ' .
        'WHERE `cluster_name` = ' . $options{module}->{db_gorgone}->quote($options{cluster})
    );
    if ($status == -1) {
        $options{module}->{logger}->writeLogError("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_MIGRATION_UPDATE_SQLITE: cannot update sqlite");
        send_log(
            module => $options{module},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{clusters}->{ $options{cluster} }->{live}->{token},
            data => { message => 'cannot update sqlite' }
        );
        return -1;
    }

    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');
    $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_MIGRATION_UPDATE_SQLITE finished");
    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');

    ########
    # Step 2
    ########
    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');
    $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_MIGRATION_UPDATE_CENTREON_DB started");
    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');
    $options{clusters}->{ $options{cluster} }->{live}->{state} = STATE_MIGRATION_UPDATE_CENTREON_DB;
    send_log(module => $options{module}, code => GORGONE_MODULE_CENTREON_JUDGE_FAILOVER_RUNNING, live => $options{clusters}->{ $options{cluster} }->{live});

    ($status) = $options{module}->{class_object_centreon}->custom_execute(
        request => 'UPDATE ns_host_relation SET nagios_server_id = ' . $options{module}->{class_object_centreon}->quote(value => $options{clusters}->{ $options{cluster} }->{spare}) .
            ' WHERE host_host_id IN (' . join(',', @{$data->{hosts}}) . ')'
    );
    if ($status == -1) {
        $options{module}->{logger}->writeLogError("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_MIGRATION_UPDATE_CENTREON_DB: cannot update database");
        send_log(
            module => $options{module},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{clusters}->{ $options{cluster} }->{live}->{token},
            data => { message => 'cannot update database' }
        );
        return -1;
    }

    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');
    $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_MIGRATION_UPDATE_CENTREON_DB finished");
    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');

    ########
    # Step 3
    ########
    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');
    $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_MIGRATION_GENERATE_CONFIGS started");
    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');
    $options{clusters}->{ $options{cluster} }->{live}->{state} = STATE_MIGRATION_GENERATE_CONFIGS;
    $options{clusters}->{ $options{cluster} }->{live}->{token_config_node_failed} = 'judge-spare##' . $options{clusters}->{ $options{cluster} }->{name} . '##' . STATE_MIGRATION_GENERATE_CONFIGS . '##' . $options{module}->generate_token(length => 8); 
    $options{clusters}->{ $options{cluster} }->{live}->{token_config_node_spare} = 'judge-spare##' . $options{clusters}->{ $options{cluster} }->{name} . '##' . STATE_MIGRATION_GENERATE_CONFIGS . '##' . $options{module}->generate_token(length => 8); 
    send_log(module => $options{module}, code => GORGONE_MODULE_CENTREON_JUDGE_FAILOVER_RUNNING, live => $options{clusters}->{ $options{cluster} }->{live});

    $options{module}->send_internal_action(
        action => 'ADDLISTENER',
        data => [
            {
                identity => 'gorgonejudge',
                event => 'JUDGELISTENER',
                token => $options{clusters}->{ $options{cluster} }->{live}->{token_config_node_failed},
                timeout => 200
            }
        ]
    );
    $options{module}->send_internal_action(
        action => 'ADDPIPELINE',
        token => $options{clusters}->{ $options{cluster} }->{live}->{token_config_node_failed},
        timeout => 180,
        data => [
            {
                action => 'COMMAND',
                timeout => 150,
                data => {
                    content => [ {
                        command => 'centreon -u ' . $options{module}->{clapi_user} . ' -p ' . $options{module}->{clapi_password} . ' -a POLLERGENERATE -v ' . $options{node_src},
                    } ] 
                }
            }
        ]
    );

    $options{module}->send_internal_action(
        action => 'ADDLISTENER',
        data => [
            {
                identity => 'gorgonejudge',
                event => 'JUDGELISTENER',
                token => $options{clusters}->{ $options{cluster} }->{live}->{token_config_node_spare},
                timeout => 200
            }
        ]
    );
    $options{module}->send_internal_action(
        action => 'ADDPIPELINE',
        token => $options{clusters}->{ $options{cluster} }->{live}->{token_config_node_spare},
        timeout => 180,
        data => [
            {
                action => 'COMMAND',
                timeout => 150,
                data => {
                    content => [ {
                        command => 'centreon -u ' . $options{module}->{clapi_user} . ' -p ' . $options{module}->{clapi_password} . ' -a POLLERGENERATE -v ' . $options{clusters}->{ $options{cluster} }->{spare},
                    } ] 
                }
            }
        ]
    );

    return 0;
}

sub migrate_step_3 {
    my (%options) = @_;

    return 0 if ($options{code} != GORGONE_ACTION_FINISH_KO && $options{code} != GORGONE_ACTION_FINISH_OK);
    return 0 if ($options{token} ne $options{clusters}->{ $options{cluster} }->{live}->{token_config_node_failed} &&
        $options{token} ne $options{clusters}->{ $options{cluster} }->{live}->{token_config_node_spare});

    if ($options{code} == GORGONE_ACTION_FINISH_KO) {
        $options{module}->{logger}->writeLogError("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_MIGRATION_GENERATE_CONFIGS: generate config error");
        $options{clusters}->{ $options{cluster} }->{live}->{status} = FAILOVER_FAIL_STATUS;
        update_status(
            module => $options{module},
            cluster => $options{cluster},
            status => FAILOVER_FAIL_STATUS,
            step => 'STATE_MIGRATION_GENERATE_CONFIGS'
        );
        send_log(
            module => $options{module},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{clusters}->{ $options{cluster} }->{live}->{token},
            data => { message => 'generate config error' }
        );
        return -1;
    }
    
    $options{clusters}->{ $options{cluster} }->{live}->{token_config_responses}++;
    if ($options{clusters}->{ $options{cluster} }->{live}->{token_config_responses} < 2) {
        return 0;
    }

    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');
    $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_MIGRATION_GENERATE_CONFIGS finished");
    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');

    ########
    # Step 4
    ########
    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');
    $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_MIGRATION_POLLER_FAILED started");
    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');
    $options{clusters}->{ $options{cluster} }->{live}->{state} = STATE_MIGRATION_POLLER_FAILED;
    $options{clusters}->{ $options{cluster} }->{live}->{token_pipeline_node_failed} = 'judge-spare##' . $options{clusters}->{ $options{cluster} }->{name} . '##' . STATE_MIGRATION_POLLER_FAILED . '##' . $options{module}->generate_token(length => 8);
    send_log(module => $options{module}, code => GORGONE_MODULE_CENTREON_JUDGE_FAILOVER_RUNNING, live => $options{clusters}->{ $options{cluster} }->{live});

    $options{module}->send_internal_action(
        action => 'ADDLISTENER',
        data => [
            {
                identity => 'gorgonejudge',
                event => 'JUDGELISTENER',
                token => $options{clusters}->{ $options{cluster} }->{live}->{token_pipeline_node_failed},
                timeout => 450
            }
        ]
    );
    $options{module}->add_pipeline_config_reload_poller(
        token => $options{clusters}->{ $options{cluster} }->{live}->{token_pipeline_node_failed},
        poller_id => $options{clusters}->{ $options{cluster} }->{live}->{node_src},
        no_generate_config => 1,
        pipeline_timeout => 400
    );

    return 0;
}

sub migrate_step_4 {
    my (%options) = @_;

    return 0 if ($options{code} != GORGONE_ACTION_FINISH_KO && $options{code} != GORGONE_ACTION_FINISH_OK);

    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');
    $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_MIGRATION_POLLER_FAILED finished");
    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');

    ########
    # Step 5
    ########
    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');
    $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_MIGRATION_POLLER_SPARE started");
    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');
    $options{clusters}->{ $options{cluster} }->{live}->{state} = STATE_MIGRATION_POLLER_SPARE;
    $options{clusters}->{ $options{cluster} }->{live}->{token_pipeline_node_spare} = 'judge-spare##' . $options{clusters}->{ $options{cluster} }->{name} . '##' . STATE_MIGRATION_POLLER_SPARE . '##' . $options{module}->generate_token(length => 8); 
    send_log(module => $options{module}, code => GORGONE_MODULE_CENTREON_JUDGE_FAILOVER_RUNNING, live => $options{clusters}->{ $options{cluster} }->{live});

    $options{module}->send_internal_action(
        action => 'ADDLISTENER',
        data => [
            {
                identity => 'gorgonejudge',
                event => 'JUDGELISTENER',
                token => $options{clusters}->{ $options{cluster} }->{live}->{token_pipeline_node_spare},
                timeout => 450
            }
        ]
    );
    $options{module}->add_pipeline_config_reload_poller(
        token => $options{clusters}->{ $options{cluster} }->{live}->{token_pipeline_node_spare},
        poller_id => $options{clusters}->{ $options{cluster} }->{spare},
        no_generate_config => 1,
        pipeline_timeout => 400
    );
}

sub migrate_step_5 {
    my (%options) = @_;

    return 0 if ($options{code} != GORGONE_ACTION_FINISH_KO && $options{code} != GORGONE_ACTION_FINISH_OK);

    if ($options{code} == GORGONE_ACTION_FINISH_KO) {
        $options{module}->{logger}->writeLogError("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_MIGRATION_POLLER_SPARE: pipeline error");
        $options{clusters}->{ $options{cluster} }->{status} = FAILOVER_FAIL_STATUS;
        update_status(
            module => $options{module},
            cluster => $options{cluster},
            status => FAILOVER_FAIL_STATUS,
            step => 'STATE_MIGRATION_POLLER_SPARE'
        );
        send_log(
            module => $options{module},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{clusters}->{ $options{cluster} }->{live}->{token},
            data => { message => 'pipeline error' }
        );
        return -1;
    }

    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');
    $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_MIGRATION_POLLER_SPARE finished");
    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');

    ########
    # Step 6
    ########
    $options{clusters}->{ $options{cluster} }->{live}->{state} = STATE_MIGRATION_UPDATE_RUNNING_POLLER_FAILED;
    send_log(module => $options{module}, code => GORGONE_MODULE_CENTREON_JUDGE_FAILOVER_RUNNING, live => $options{clusters}->{ $options{cluster} }->{live});

    if (!defined($options{clusters}->{ $options{cluster} }->{live}->{no_update_running_failed}) || 
        $options{clusters}->{ $options{cluster} }->{live}->{no_update_running_failed} != 1) {
        my ($status) = $options{module}->{class_object_centstorage}->custom_execute(
            request => 'UPDATE instances SET running = 0 ' .
                ' WHERE instance_id = ' . $options{module}->{class_object_centstorage}->quote(value => $options{clusters}->{ $options{cluster} }->{live}->{node_src})
        );
        if ($status == -1) {
            $options{module}->{logger}->writeLogError("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_MIGRATION_UPDATE_RUNNING_POLLER_FAILED: cannot update database");
        }
    }

    $options{clusters}->{ $options{cluster} }->{live}->{status} = FAILOVER_SUCCESS_STATUS;
    update_status(
        module => $options{module},
        cluster => $options{cluster},
        status => FAILOVER_SUCCESS_STATUS,
        step => 'STATE_MIGRATION_POLLER_SPARE'
    );
    send_log(
        module => $options{module},
        code => GORGONE_ACTION_FINISH_OK,
        token => $options{clusters}->{ $options{cluster} }->{live}->{token},
        data => { message => 'failover finished' }
    );

    return 0;
}
sub migrate_steps_listener_response {
    my (%options) = @_;

    return -1 if (!defined($options{clusters}->{ $options{cluster} }));
    if ($options{state} != $options{clusters}->{ $options{cluster} }->{live}->{state}) {
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

    if ($options{state} == STATE_FAILBACK_GENERATE_CONFIGS) {
        return failback_generate_configs(%options);
    }
    if ($options{state} == STATE_FAILBACK_POLLER_SRC) {
        return failback_poller_src(%options);
    }
    if ($options{state} == STATE_FAILBACK_POLLER_DST) {
        return failback_poller_dst(%options);
    }
}

=pod

**********************
Failback migrate steps
**********************

=cut

sub failback_start {
    my (%options) = @_;

    $options{clusters}->{ $options{cluster} }->{live}->{token} = $options{token};
    $options{clusters}->{ $options{cluster} }->{live}->{status} = FAILBACK_RUNNING_STATUS;
    $options{clusters}->{ $options{cluster} }->{live}->{token_config_node_src} = undef;
    $options{clusters}->{ $options{cluster} }->{live}->{token_config_node_dst} = undef;
    $options{clusters}->{ $options{cluster} }->{live}->{token_config_responses} = 0;
    $options{clusters}->{ $options{cluster} }->{live}->{token_pipeline_node_src} = undef;
    $options{clusters}->{ $options{cluster} }->{live}->{token_pipeline_node_dst} = undef;
    $options{clusters}->{ $options{cluster} }->{live}->{node_src} = $options{clusters}->{ $options{cluster} }->{spare};
    $options{clusters}->{ $options{cluster} }->{live}->{node_dst} = undef;

    ########
    # Step 1
    ########
    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');
    $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_FAILBACK_GET_SQLITE started");
    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');
    $options{clusters}->{ $options{cluster} }->{live}->{state} = STATE_FAILBACK_GET_SQLITE;
    send_log(module => $options{module}, code => GORGONE_MODULE_CENTREON_JUDGE_FAILBACK_RUNNING, live => $options{clusters}->{ $options{cluster} }->{live});

    my $query = 'SELECT `status`, `data` FROM gorgone_centreon_judge_spare WHERE cluster_name = ' . $options{module}->{db_gorgone}->quote($options{clusters}->{ $options{cluster} }->{name});
    my ($status, $sth) = $options{module}->{db_gorgone}->query($query);
    if ($status == -1) {
        $options{module}->{logger}->writeLogError("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' cannot get sqlite information");
        send_log(
            module => $options{module},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{clusters}->{ $options{cluster} }->{live}->{token},
            data => { message => 'cannot get sqlite information' }
        );        
        return -1;
    }
    my $row = $sth->fetchrow_hashref();
    if (!defined($row)) {
        $options{module}->{logger}->writeLogError("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' no data in sqlite");
        send_log(
            module => $options{module},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{clusters}->{ $options{cluster} }->{live}->{token},
            data => { message => 'no data in sqlite' }
        );        
        return -1;
    }
    ($status, my $decoded) = $options{module}->json_decode(
        argument => $row->{data},
        method => "-class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' cannot decode json information"
    );
    if ($status == 1) {
        $options{module}->{logger}->writeLogError("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' cannot decode json");
        send_log(
            module => $options{module},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{clusters}->{ $options{cluster} }->{live}->{token},
            data => { message => 'cannot decode json' }
        );
        return -1;
    }

    $options{clusters}->{ $options{cluster} }->{live}->{node_dst} = $decoded->{node_src};

    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');
    $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_FAILBACK_GET_SQLITE finished");
    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');

    ########
    # Step 2
    ########
    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');
    $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_FAILBACK_UPDATE_CENTREON_DB started");
    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');
    $options{clusters}->{ $options{cluster} }->{live}->{state} = STATE_FAILBACK_UPDATE_CENTREON_DB;
    send_log(module => $options{module}, code => GORGONE_MODULE_CENTREON_JUDGE_FAILBACK_RUNNING, live => $options{clusters}->{ $options{cluster} }->{live});

    ($status) = $options{module}->{class_object_centreon}->custom_execute(
        request => 'UPDATE ns_host_relation SET nagios_server_id = ' . $options{module}->{class_object_centreon}->quote(value => $options{clusters}->{ $options{cluster} }->{live}->{node_dst}) .
            ' WHERE host_host_id IN (' . join(',', @{$decoded->{hosts}}) . ')'
    );
    if ($status == -1) {
        $options{module}->{logger}->writeLogError("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_FAILBACK_UPDATE_CENTREON_DB: cannot update database");
        send_log(
            module => $options{module},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{clusters}->{ $options{cluster} }->{live}->{token},
            data => { message => 'cannot update database' }
        );
        return -1;
    }

    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');
    $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_FAILBACK_UPDATE_CENTREON_DB finished");
    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');

    ########
    # Step 3
    ########
    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');
    $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_FAILBACK_GENERATE_CONFIGS started");
    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');
    $options{clusters}->{ $options{cluster} }->{live}->{state} = STATE_FAILBACK_GENERATE_CONFIGS;
    $options{clusters}->{ $options{cluster} }->{live}->{token_config_node_src} = 'judge-spare##' . $options{clusters}->{ $options{cluster} }->{name} . '##' . STATE_FAILBACK_GENERATE_CONFIGS . '##' . $options{module}->generate_token(length => 8); 
    $options{clusters}->{ $options{cluster} }->{live}->{token_config_node_dst} = 'judge-spare##' . $options{clusters}->{ $options{cluster} }->{name} . '##' . STATE_FAILBACK_GENERATE_CONFIGS . '##' . $options{module}->generate_token(length => 8); 
    send_log(module => $options{module}, code => GORGONE_MODULE_CENTREON_JUDGE_FAILBACK_RUNNING, live => $options{clusters}->{ $options{cluster} }->{live});

    $options{module}->send_internal_action(
        action => 'ADDLISTENER',
        data => [
            {
                identity => 'gorgonejudge',
                event => 'JUDGELISTENER',
                token => $options{clusters}->{ $options{cluster} }->{live}->{token_config_node_src},
                timeout => 200
            }
        ]
    );
    $options{module}->send_internal_action(
        action => 'ADDPIPELINE',
        token => $options{clusters}->{ $options{cluster} }->{live}->{token_config_node_src},
        timeout => 180,
        data => [
            {
                action => 'COMMAND',
                timeout => 150,
                data => {
                    content => [ {
                        command => 'centreon -u ' . $options{module}->{clapi_user} . ' -p ' . $options{module}->{clapi_password} . ' -a POLLERGENERATE -v ' . $options{clusters}->{ $options{cluster} }->{live}->{node_src}
                    } ] 
                }
            }
        ]
    );

    $options{module}->send_internal_action(
        action => 'ADDLISTENER',
        data => [
            {
                identity => 'gorgonejudge',
                event => 'JUDGELISTENER',
                token => $options{clusters}->{ $options{cluster} }->{live}->{token_config_node_dst},
                timeout => 200
            }
        ]
    );
    $options{module}->send_internal_action(
        action => 'ADDPIPELINE',
        token => $options{clusters}->{ $options{cluster} }->{live}->{token_config_node_dst},
        timeout => 180,
        data => [
            {
                action => 'COMMAND',
                timeout => 150,
                data => {
                    content => [ {
                        command => 'centreon -u ' . $options{module}->{clapi_user} . ' -p ' . $options{module}->{clapi_password} . ' -a POLLERGENERATE -v ' . $options{clusters}->{ $options{cluster} }->{live}->{node_dst}
                    } ] 
                }
            }
        ]
    );

    return 0;
}

sub failback_generate_configs {
    my (%options) = @_;

    return 0 if ($options{code} != GORGONE_ACTION_FINISH_KO && $options{code} != GORGONE_ACTION_FINISH_OK);
    return 0 if ($options{token} ne $options{clusters}->{ $options{cluster} }->{live}->{token_config_node_src} &&
        $options{token} ne $options{clusters}->{ $options{cluster} }->{live}->{token_config_node_dst});

    if ($options{code} == GORGONE_ACTION_FINISH_KO) {
        $options{module}->{logger}->writeLogError("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_FAILBACK_GENERATE_CONFIGS: generate config error");
        $options{clusters}->{ $options{cluster} }->{live}->{status} = FAILBACK_FAIL_STATUS;
        update_status(
            module => $options{module},
            cluster => $options{cluster},
            status => FAILBACK_FAIL_STATUS,
            step => 'STATE_FAILBACK_GENERATE_CONFIGS'
        );
        send_log(
            module => $options{module},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{clusters}->{ $options{cluster} }->{live}->{token},
            data => { message => 'generate config error' }
        );
        return -1;
    }
    
    $options{clusters}->{ $options{cluster} }->{live}->{token_config_responses}++;
    if ($options{clusters}->{ $options{cluster} }->{live}->{token_config_responses} < 2) {
        return 0;
    }

    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');
    $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_FAILBACK_GENERATE_CONFIGS finished");
    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');

    ########
    # Step 4
    ########
    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');
    $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_FAILBACK_POLLER_SRC started");
    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');
    $options{clusters}->{ $options{cluster} }->{live}->{state} = STATE_FAILBACK_POLLER_SRC;
    $options{clusters}->{ $options{cluster} }->{live}->{token_pipeline_node_src} = 'judge-spare##' . $options{clusters}->{ $options{cluster} }->{name} . '##' . STATE_FAILBACK_POLLER_SRC . '##' . $options{module}->generate_token(length => 8);
    send_log(module => $options{module}, code => GORGONE_MODULE_CENTREON_JUDGE_FAILBACK_RUNNING, live => $options{clusters}->{ $options{cluster} }->{live});

    $options{module}->send_internal_action(
        action => 'ADDLISTENER',
        data => [
            {
                identity => 'gorgonejudge',
                event => 'JUDGELISTENER',
                token => $options{clusters}->{ $options{cluster} }->{live}->{token_pipeline_node_src},
                timeout => 450
            }
        ]
    );
    $options{module}->add_pipeline_config_reload_poller(
        token => $options{clusters}->{ $options{cluster} }->{live}->{token_pipeline_node_src},
        poller_id => $options{clusters}->{ $options{cluster} }->{live}->{node_src},
        no_generate_config => 1,
        pipeline_timeout => 400
    );

    return 0;
}

sub failback_poller_src {
    my (%options) = @_;

    return 0 if ($options{code} != GORGONE_ACTION_FINISH_KO && $options{code} != GORGONE_ACTION_FINISH_OK);

    if ($options{code} == GORGONE_ACTION_FINISH_KO) {
        $options{module}->{logger}->writeLogError("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_FAILBACK_POLLER_SRC: pipeline error");
        $options{clusters}->{ $options{cluster} }->{status} = FAILBACK_FAIL_STATUS;
        update_status(
            module => $options{module},
            cluster => $options{cluster},
            status => FAILBACK_FAIL_STATUS,
            step => 'STATE_FAILBACK_POLLER_SRC'
        );
        send_log(
            module => $options{module},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{clusters}->{ $options{cluster} }->{live}->{token},
            data => { message => 'pipeline error' }
        );
        return -1;
    }

    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');
    $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_FAILBACK_POLLER_SRC finished");
    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');

    ########
    # Step 5
    ########
    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');
    $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_FAILBACK_POLLER_DST started");
    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');
    $options{clusters}->{ $options{cluster} }->{live}->{state} = STATE_FAILBACK_POLLER_DST;
    $options{clusters}->{ $options{cluster} }->{live}->{token_pipeline_node_dst} = 'judge-spare##' . $options{clusters}->{ $options{cluster} }->{name} . '##' . STATE_FAILBACK_POLLER_DST . '##' . $options{module}->generate_token(length => 8); 
    send_log(module => $options{module}, code => GORGONE_MODULE_CENTREON_JUDGE_FAILBACK_RUNNING, live => $options{clusters}->{ $options{cluster} }->{live});

    $options{module}->send_internal_action(
        action => 'ADDLISTENER',
        data => [
            {
                identity => 'gorgonejudge',
                event => 'JUDGELISTENER',
                token => $options{clusters}->{ $options{cluster} }->{live}->{token_pipeline_node_dst},
                timeout => 450
            }
        ]
    );
    $options{module}->add_pipeline_config_reload_poller(
        token => $options{clusters}->{ $options{cluster} }->{live}->{token_pipeline_node_dst},
        poller_id => $options{clusters}->{ $options{cluster} }->{live}->{node_dst},
        no_generate_config => 1,
        pipeline_timeout => 400
    );
}

sub failback_poller_dst {
    my (%options) = @_;

    return 0 if ($options{code} != GORGONE_ACTION_FINISH_KO && $options{code} != GORGONE_ACTION_FINISH_OK);

    if ($options{code} == GORGONE_ACTION_FINISH_KO) {
        $options{module}->{logger}->writeLogError("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_FAILBACK_POLLER_DST: pipeline error");
        $options{clusters}->{ $options{cluster} }->{status} = FAILBACK_FAIL_STATUS;
        update_status(
            module => $options{module},
            cluster => $options{cluster},
            status => FAILBACK_FAIL_STATUS,
            step => 'STATE_FAILBACK_POLLER_DST'
        );
        send_log(
            module => $options{module},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{clusters}->{ $options{cluster} }->{live}->{token},
            data => { message => 'pipeline error' }
        );
        return -1;
    }

    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');
    $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{clusters}->{ $options{cluster} }->{name} . "' step STATE_FAILBACK_POLLER_DST finished");
    $options{module}->{logger}->writeLogInfo('[judge] -class- ************************************');

    $options{clusters}->{ $options{cluster} }->{live}->{status} = FAILBACK_SUCCESS_STATUS;
    update_status(
        module => $options{module},
        cluster => $options{cluster},
        status => FAILBACK_SUCCESS_STATUS,
        step => 'STATE_FAILBACK_POLLER_DST'
    );
    send_log(
        module => $options{module},
        code => GORGONE_ACTION_FINISH_OK,
        token => $options{clusters}->{ $options{cluster} }->{live}->{token},
        data => { message => 'failback finished' }
    );

    return 0;
}

1;
