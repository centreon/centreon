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

package gorgone::modules::centreon::autodiscovery::services::discovery;

use base qw(gorgone::class::module);

use strict;
use warnings;
use gorgone::standard::library;
use gorgone::standard::constants qw(:all);
use gorgone::modules::centreon::autodiscovery::services::resources;
use ZMQ::LibZMQ4;
use ZMQ::Constants qw(:all);
use XML::Simple;

sub new {
    my ($class, %options) = @_;

    my $connector  = {};
    $connector->{internal_socket} = $options{internal_socket};
    $connector->{module_id} = $options{module_id};
    $connector->{logger} = $options{logger};
    $connector->{config} = $options{config};
    $connector->{config_core} = $options{config_core};

    $connector->{service_pollers} = {};
    $connector->{service_audit_user_id} = undef;
    $connector->{service_parrallel_commands_poller} = 8;
    $connector->{service_current_commands_poller} = {};
    $connector->{finished} = 0;

    bless $connector, $class;
    $connector->{uuid} = $connector->generate_token(length => 4) . ':' . $options{service_number};
    return $connector;
}

sub database_init_transaction {
    my ($self, %options) = @_;
    
    eval {
        $self->{class_object_centreon}->{db_centreon}->transaction_mode(1);
    };
    if ($@) {
        $self->{logger}->writeLogError("$@");
        return -1;
    }
    return 0;
}

sub database_commit_transaction {
    my ($self, %options) = @_;
    
    eval {
        $self->{class_object_centreon}->commit();
        $self->{class_object_centreon}->transaction_mode(0);
    };
    if ($@) {
        $self->{logger}->writeLogError("$@");
        return -1;
    }
    return 0;
}

sub database_error_rollback {
    my ($self, %options) = @_;

    $self->{logger}->writeLogError($options{message});
    eval {
        $self->{class_object_centreon}->rollback();
        $self->{class_object_centreon}->transaction_mode(0);
    };
    if ($@) {
        $self->{logger}->writeLogError("$@");
    }
    return -1;
}

sub get_uuid {
    my ($self, %options) = @_;

    return $self->{uuid};
}

sub is_finished {
    my ($self, %options) = @_;

    return $self->{finished};
}

sub get_clapi_user {
    my ($self, %options) = @_;

    $self->{clapi_user} = $self->{config}->{clapi_user};
    $self->{clapi_password} = $self->{config}->{clapi_password};

    if (!defined($self->{clapi_password})) {
        return (-1, 'cannot get configuration for CLAPI user');
    }

    return 0;
}

sub get_description {
    my ($self, %options) = @_;
    
    my $description = $options{discovery_svc}->{service_name};
    if (defined($self->{discovery}->{rules}->{ $options{rule_id} }->{rule_scan_display_custom}) && $self->{discovery}->{rules}->{ $options{rule_id} }->{rule_scan_display_custom} ne '') {
        my $error;
        local $SIG{__DIE__} = sub { $error = $_[0]; };

        eval "$self->{discovery}->{rules}->{ $options{rule_id} }->{rule_scan_display_custom}";
        if (defined($error)) {
            $self->{logger}->writeLogError("$options{logger_pre_message} [" . $options{discovery_svc}->{service_name} . "] custom description code execution problem: " . $error);
        }
    }
    
    return $description;
}

sub link_service_autodisco {
    my ($self, %options) = @_;
    
    my $query = 'INSERT IGNORE INTO mod_auto_disco_rule_service_relation (rule_rule_id, service_service_id) VALUES (' . $options{rule_id} . ', ' . $options{service_id} . ')';
    my ($status, $sth) = $self->{class_object_centreon}->custom_execute(request => $query);
    if ($status == -1) {
        return -1;
    }
    
    return 0;
}

sub create_service {
    my ($self, %options) = @_;
    
    if ($self->{discovery}->{manual} == 1) {
        $self->{discovery}->{rules}->{ $options{rule_id} }->{manual}->{ $options{host_id} } = { discovery => {} }
            if (!defined($self->{discovery}->{rules}->{ $options{rule_id} }->{manual}->{ $options{host_id} }));
        $self->{discovery}->{rules}->{ $options{rule_id} }->{manual}->{ $options{host_id} }->{discovery}->{ $options{discovery_svc}->{service_name} } = { 
            type => 1, 
            service_template_model_stm_id => $self->{discovery}->{rules}->{ $options{rule_id} }->{service_template_model_id},
            macros => {},
            description => $self->get_description(%options),
        };
        foreach (keys %{$options{macros}}) {
            $self->{discovery}->{rules}->{ $options{rule_id} }->{manual}->{ $options{host_id} }->{discovery}->{ $options{discovery_svc}->{service_name} }->{macros}->{$_} = {
                value => $options{macros}->{$_},
                type => 1
            };
        }
    }

    return 0 if ($self->{discovery}->{dry_run} == 1);
    # We create the service

    return -1 if ($self->database_init_transaction() == -1);

    my $query = 'INSERT INTO service (service_template_model_stm_id, service_description, service_register) VALUES (' . $self->{class_object_centreon}->quote(value => $self->{discovery}->{rules}->{ $options{rule_id} }->{service_template_model_id}) . ', ' . $self->{class_object_centreon}->quote(value => $options{discovery_svc}->{service_name}) . ", '1')";
    my ($status, $sth) = $self->{class_object_centreon}->custom_execute(request => $query);
    if ($status == -1) {
        return $self->database_error_rollback(message => "$options{logger_pre_message} [" . $options{discovery_svc}->{service_name} . "] -> cannot create service");
    }
    my $service_id = $self->{class_object_centreon}->{db_centreon}->last_insert_id();
    $self->{logger}->writeLogError("$options{logger_pre_message} [" . $options{discovery_svc}->{service_name} . "] -> PLOP last insert id = $service_id");
    
    $query = 'INSERT INTO host_service_relation (host_host_id, service_service_id) VALUES (' . $options{host_id} . ', ' . $service_id . ')';
    ($status) = $self->{class_object_centreon}->custom_execute(request => $query);
    if ($status == -1) {
        return $self->database_error_rollback(message => "$options{logger_pre_message} [" . $options{discovery_svc}->{service_name} . "] -> cannot link service to host");
    }
    
    $query = 'INSERT INTO extended_service_information (service_service_id) VALUES (' . $service_id . ')';
    ($status) = $self->{class_object_centreon}->custom_execute(request => $query);
    if ($status == -1) {
        return $self->database_error_rollback(message => "$options{logger_pre_message} [" . $options{discovery_svc}->{service_name} . "] -> cannot service extended information");
    }
    
    foreach (keys %{$options{macros}}) {
        $query = 'INSERT INTO on_demand_macro_service (svc_svc_id, svc_macro_name, svc_macro_value) VALUES (' . $service_id . ', ' . $self->{class_object_centreon}->quote(value => '$_SERVICE' . $_ . '$') . ', ' . $self->{class_object_centreon}->quote(value => $options{macros}->{$_}) . ')';
        ($status) = $self->{class_object_centreon}->custom_execute(request => $query);
        if ($status == -1) {
            return $self->database_error_rollback(message => "$options{logger_pre_message} [" . $options{discovery_svc}->{service_name} . "] -> cannot create macro '$_' => '$options{macros}->{$_}'");
        }
    }

    if ($self->link_service_autodisco(%options, service_id => $service_id) == -1) {
        return $self->database_error_rollback(message => "$options{logger_pre_message} [" . $options{discovery_svc}->{service_name} . "] -> cannot link service to autodisco");
    }
    
    return -1 if ($self->database_commit_transaction() == -1);
    
    $self->{discovery}->{pollers_reload}->{ $options{poller_id} } = 1;

    # TODO
    #$self->audit_update(
    #    object_type => 'service', 
    #    action_type => 'a', 
    #    object_id => $service_id, 
    #    object_name => $self->{service_name},
    #    contact_id => $self->{audit_user_id},
    #    fields => {
    #        service_template_model_id => $self->{rule}->{service_template_model_id}, 
    #        service_description => $self->{service_name}, 
    #        service_register => '1', 
    #        service_hPars => $options{host}->{host_id}
    #    }
    #);

    return $service_id;
}

sub crud_service {
    my ($self, %options) = @_;
    
    my $service_id;
    if (!defined($options{service})) {
        $service_id = $self->create_service(%options);
        $self->{logger}->writeLogInfo("$options{logger_pre_message} [" . $options{discovery_svc}->{service_name} . "] -> service created");
        if ($service_id != -1) {
            push @{$self->{discovery}->{journal}}, {
                host_name => $self->{discovery}->{hosts}->{ $options{host_id} }->{host_name},
                service_name => $options{discovery_svc}->{service_name},
                type => 'created'
            };
        }
    } else {
        # TODO
        #$service_id = $self->update_service(%options);
    }
    
    return 0;
}

sub service_response_parsing {
    my ($self, %options) = @_;

    my $rule_alias = $self->{discovery}->{rules}->{ $options{rule_id} }->{rule_alias};
    my $poller_name = $self->{service_pollers}->{ $options{poller_id} }->{name};
    my $host_name = $self->{discovery}->{hosts}->{ $options{host_id} }->{host_name};
    my $logger_pre_message = "[autodiscovery] -servicediscovery- $self->{uuid} [" . $rule_alias . "] [" . $poller_name . "] [" . $host_name . "]";

    my $xml;
    eval {
        $xml = XMLin($options{response}, ForceArray => 1, KeyAttr => []);
    };
    if ($@) {
        if ($self->{discovery}->{manual} == 1) {
            $self->{discovery}->{rules}->{ $options{rule_id} }->{manual}->{ $options{host_id} } = {
                message => 'load xml issue', discovery => {}
            };
        }
        $self->{logger}->writeLogError("$logger_pre_message -> load xml issue");
        $self->{logger}->writeLogDebug("$logger_pre_message -> load xml error: $@");
        return -1;
    }

    my $discovery_svc = { discovered_services => {} };
    foreach my $attributes (@{$xml->{label}}) {
        $discovery_svc->{service_name} = '';
        $discovery_svc->{attributes} = $attributes;
        gorgone::modules::centreon::autodiscovery::services::resources::change_vars(
            discovery_svc => $discovery_svc,
            rule => $self->{discovery}->{rules}->{ $options{rule_id} },
            logger => $self->{logger},
            logger_pre_message => $logger_pre_message
        );
        if ($discovery_svc->{service_name} eq '') {
            $self->{logger}->writeLogError("$logger_pre_message -> no value for service name");
            next;
        }

        if (defined($discovery_svc->{discovered_services}->{  $discovery_svc->{service_name} })) {
            $self->{logger}->writeLogError("$logger_pre_message -> service '" .  $discovery_svc->{service_name} . "' already created");
            next;
        }

        $discovery_svc->{discovered_services}->{  $discovery_svc->{service_name} } = 1;

        next if (
            gorgone::modules::centreon::autodiscovery::services::resources::check_exinc(
                discovery_svc => $discovery_svc,
                rule => $self->{discovery}->{rules}->{ $options{rule_id} },
                logger => $self->{logger},
                logger_pre_message => $logger_pre_message
            )
        );
        gorgone::modules::centreon::autodiscovery::services::resources::custom_variables(
            discovery_svc => $discovery_svc,
            rule => $self->{discovery}->{rules}->{ $options{rule_id} },
            logger => $self->{logger},
            logger_pre_message => $logger_pre_message
        );
        my $macros = gorgone::modules::centreon::autodiscovery::services::resources::get_macros(
            discovery_svc => $discovery_svc,
            rule => $self->{discovery}->{rules}->{ $options{rule_id} }
        );
        
        my ($status, $service) = gorgone::modules::centreon::autodiscovery::services::resources::get_service(
            class_object_centreon => $self->{class_object_centreon},
            host_id => $options{host_id},
            service_name => $discovery_svc->{service_name},
            logger => $self->{logger},
            logger_pre_message => $logger_pre_message
        );
        next if ($status == -1);

        $self->{logger}->writeLogDebug("$logger_pre_message -> service '" .  $discovery_svc->{service_name} . "' ici");
        $self->crud_service(
            discovery_svc => $discovery_svc,
            rule_id => $options{rule_id},
            host_id => $options{host_id},
            poller_id => $options{poller_id},
            service => $service,
            macros => $macros,
            logger_pre_message => $logger_pre_message
        );
    }

    # TODO
    #$self->disable_services(host => $options{host});
}

sub discoverylistener {
    my ($self, %options) = @_;

    # if i have GORGONE_MODULE_ACTION_COMMAND_RESULT, i can't have GORGONE_ACTION_FINISH_KO
    if ($options{data}->{code} == GORGONE_MODULE_ACTION_COMMAND_RESULT) {
        my $exit_code = $options{data}->{data}->{result}->{exit_code};
        if ($exit_code == 0) {
            $self->service_response_parsing(
                rule_id => $options{rule_id},
                host_id => $options{host_id},
                poller_id => $self->{discovery}->{hosts}->{ $options{host_id} }->{poller_id},
                response => $options{data}->{data}->{result}->{stdout}
            );
        } elsif ($self->{discovery}->{manual} == 1) {
            $self->{discovery}->{rules}->{ $options{rule_id} }->{manual}->{ $options{host_id} } = { message => $options{data}->{data}->{message}, data => $options{data}->{data}, discovery => {} };
        }
    } elsif ($options{data}->{code} == GORGONE_ACTION_FINISH_KO) {
        if ($self->{discovery}->{manual} == 1) {
            $self->{discovery}->{rules}->{ $options{rule_id} }->{manual}->{ $options{host_id} } = { message => $options{data}->{data}->{message}, data => $options{data}->{data}, discovery => {} };
        }
    } else {
        return 0;
    }

    $self->{service_current_commands_poller}->{ $self->{discovery}->{hosts}->{ $options{host_id} }->{poller_id} }--;
    $self->service_execute_commands();

    print "==============================================\n";
    print Data::Dumper::Dumper($self->{service_current_commands_poller});
    print Data::Dumper::Dumper($options{data});
    print "==============================================\n";

    $self->{discovery}->{done_discoveries}++;
    $self->{logger}->writeLogDebug("[autodiscovery] -servicediscovery- $self->{discovery}->{done_discoveries}/$self->{discovery}->{count_discoveries} done");
    if ($self->{discovery}->{done_discoveries} == $self->{discovery}->{count_discoveries}) {
        $self->{logger}->writeLogDebug("[autodiscovery] -servicediscovery- $self->{uuid} discovery finished");
        $self->{finished} = 1;
    }

    return 0;
}

sub service_execute_commands {
    my ($self, %options) = @_;

    foreach my $rule_id (keys %{$self->{discovery}->{rules}}) {
        foreach my $poller_id (keys %{$self->{discovery}->{rules}->{$rule_id}->{hosts}}) {
            next if (scalar(@{$self->{discovery}->{rules}->{$rule_id}->{hosts}->{$poller_id}}) <= 0);
            $self->{service_current_commands_poller}->{$poller_id} = 0 if (!defined($self->{service_current_commands_poller}->{$poller_id}));
                
            while (1) {
                last if ($self->{service_current_commands_poller}->{$poller_id} >= $self->{service_parrallel_commands_poller});
                my $host_id = shift @{$self->{discovery}->{rules}->{$rule_id}->{hosts}->{$poller_id}};
                last if (!defined($host_id));

                my $host = $self->{discovery}->{hosts}->{$host_id};
                $self->{service_current_commands_poller}->{$poller_id}++;

                my $command = gorgone::modules::centreon::autodiscovery::services::resources::substitute_service_discovery_command(
                    command_line => $self->{discovery}->{rules}->{$rule_id}->{command_line},
                    host => $host,
                    poller => $self->{service_pollers}->{$poller_id}
                );

                $self->{logger}->writeLogInfo("[autodiscovery] -servicediscovery- $self->{uuid} [" .
                    $self->{discovery}->{rules}->{$rule_id}->{rule_alias} . "] [" . 
                    $self->{service_pollers}->{$poller_id}->{name} . "] [" .
                    $host->{host_name} . "] -> substitute string: " . $command
                );

                $self->send_internal_action(
                    action => 'ADDLISTENER',
                    data => [
                        {
                            identity => 'gorgoneautodiscovery',
                            event => 'SERVICEDISCOVERYLISTENER',
                            target => $poller_id,
                            token => 'svc-disco-' . $self->{uuid} . '-' . $rule_id . '-' . $host_id,
                            timeout => 120,
                            log_pace => 15
                        }
                    ]
                );

                $self->send_internal_action(
                    action => 'COMMAND',
                    target => $poller_id,
                    token => 'svc-disco-' . $self->{uuid} . '-' . $rule_id . '-' . $host_id,
                    data => {
                        content => [
                            {
                                instant => 1,
                                command => $command,
                                timeout => 90
                            }
                        ]
                    }
                );
            }
        }
    }
}

sub launchdiscovery {
    my ($self, %options) = @_;

    $options{token} = $self->generate_token() if (!defined($options{token}));
    $self->{class_object_centreon} = $options{class_object_centreon};

    $self->{logger}->writeLogInfo("[autodiscovery] -servicediscovery- $self->{uuid} discovery start");
    $self->send_log(
        code => GORGONE_ACTION_BEGIN,
        token => $options{token},
        data => { message => 'servicediscovery start' }
    );

    ################
    # get pollers
    ################
    $self->{logger}->writeLogInfo("[autodiscovery] -servicediscovery- $self->{uuid} load pollers configuration");
    my ($status, $message, $pollers) = gorgone::modules::centreon::autodiscovery::services::resources::get_pollers(
        class_object_centreon => $self->{class_object_centreon}
    );
    if ($status < 0) {
        $self->send_log_msg_error(token => $options{token}, subname => 'servicediscovery', number => $self->{uuid}, message => $message);
        return -1;
    }
    $self->{service_pollers} = $pollers;

    ################
    # get audit user
    ################
    $self->{logger}->writeLogInfo("[autodiscovery] -servicediscovery- $self->{uuid} load audit configuration");
    ($status, $message) = $self->get_clapi_user();
    if ($status < 0) {
        $self->send_log_msg_error(token => $options{token}, subname => 'servicediscovery', number => $self->{uuid}, message => $message);
        return -1;
    }
    ($status, $message, my $user_id) = gorgone::modules::centreon::autodiscovery::services::resources::get_pollers(
        class_object_centreon => $self->{class_object_centreon}
    );
    if ($status < 0) {
        $self->send_log_msg_error(token => $options{token}, subname => 'servicediscovery', number => $self->{uuid}, message => $message);
        return -1;
    }
    $self->{service_audit_user_id} = $user_id;

    ################
    # get rules
    ################
    $self->{logger}->writeLogInfo("[autodiscovery] -servicediscovery- $self->{uuid} load rules configuration");
    
    ($status, $message, my $rules) = gorgone::modules::centreon::autodiscovery::services::resources::get_rules(
        class_object_centreon => $self->{class_object_centreon},
        filter_rules => $options{data}->{content}->{filter_rules},
        force_rule => $options{data}->{content}->{force_rule}
    );
    if ($status < 0) {
        $self->send_log_msg_error(token => $options{token}, subname => 'servicediscovery', number => $self->{uuid}, message => $message);
        return -1;
    }

    #################
    # get hosts
    #################
    gorgone::modules::centreon::autodiscovery::services::resources::reset_macro_hosts();
    my $all_hosts = {};
    my $total = 0;
    foreach my $rule_id (keys %$rules) {
        ($status, $message, my $hosts, my $count) = gorgone::modules::centreon::autodiscovery::services::resources::get_hosts(
            host_template => $rules->{$rule_id}->{host_template},
            poller_id => $rules->{$rule_id}->{poller_id},
            class_object_centreon => $self->{class_object_centreon},
            with_macro => 1,
            host_lookup => $options{data}->{content}->{filter_hosts},
            poller_lookup => $options{data}->{content}->{filter_pollers}
        );
        if ($status < 0) {
            $self->send_log_msg_error(token => $options{token}, subname => 'servicediscovery', number => $self->{uuid}, message => $message);
            return -1;
        }
        
        if (!defined($hosts) || scalar(keys %$hosts) == 0) {
            $self->{logger}->writeLogInfo("[autodiscovery] -servicediscovery- $self->{uuid} no hosts found for rule '" . $options{rule}->{rule_alias} . "'");
            next;
        }

        $total += $count;
        $rules->{$rule_id}->{manual} = {};
        $rules->{$rule_id}->{hosts} = $hosts->{pollers};
        $all_hosts = { %$all_hosts, %{$hosts->{infos}} };

        foreach (('rule_scan_display_custom', 'rule_variable_custom')) {
            if (defined($rules->{$rule_id}->{$_}) && $rules->{$rule_id}->{$_} ne '') {
                $rules->{$rule_id}->{$_} =~ s/\$([a-zA-Z_\-\.]*?)\$/\$options{discovery_svc}->{attributes}->{$1}/msg;
                $rules->{$rule_id}->{$_} =~ s/\@SERVICENAME\@/\$options{discovery_svc}->{service_name}/msg;
            }
        }
    }

    if ($total == 0) {
        $self->send_log_msg_error(token => $options{token}, subname => 'servicediscovery', number => $self->{uuid}, message => 'no hosts found');
        return -1;
    }

    $self->{discovery} = {
        token => $options{token},
        count_discoveries => $total,
        done_discoveries => 0,
        progress => 0,
        progress_div => 0,
        rules => $rules,
        manual => defined($options{data}->{content}->{manual}) ? 1 : 0,
        dry_run => defined($options{data}->{content}->{dry_run}) ? 1 : 0,
        options => defined($options{data}->{content}) ? $options{data}->{content} : {},
        hosts => $all_hosts,
        journal => [],
        pollers_reload => {}
    };

    use Data::Dumper; print Data::Dumper::Dumper($self->{discovery});

    $self->service_execute_commands();
    
    use Data::Dumper;
    print Data::Dumper::Dumper($self->{service_current_commands_poller});

    # need to manage dry-run and manual
    # add a progress bar of commands in log (we do for each 5%)

    return 0;
}

1;
