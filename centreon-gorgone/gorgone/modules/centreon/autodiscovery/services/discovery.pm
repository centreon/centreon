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
use Net::SMTP;
use XML::Simple;
use POSIX qw(strftime);
use Safe;

sub new {
    my ($class, %options) = @_;
    my $connector = $class->SUPER::new(%options);
    bless $connector, $class;

    $connector->{internal_socket} = $options{internal_socket};
    $connector->{class_object_centreon} = $options{class_object_centreon};
    $connector->{class_object_centstorage} = $options{class_object_centstorage};
    $connector->{tpapi_clapi} = $options{tpapi_clapi};
    $connector->{mail_subject} = defined($connector->{config}->{mail_subject}) ? $connector->{config}->{mail_subject} : 'Centreon Auto Discovery';
    $connector->{mail_from} = defined($connector->{config}->{mail_from}) ? $connector->{config}->{mail_from} : 'centreon-autodisco';

    $connector->{service_pollers} = {};
    $connector->{audit_user_id} = undef;
    $connector->{service_parrallel_commands_poller} = 8;
    $connector->{service_current_commands_poller} = {};
    $connector->{finished} = 0;

    $connector->{safe_display} = Safe->new();
    $connector->{safe_display}->share('$values');
    $connector->{safe_display}->share('$description');
    $connector->{safe_display}->permit_only(':default');
    $connector->{safe_display}->share_from(
        'gorgone::modules::centreon::autodiscovery::services::resources',
        ['change_bytes']
    );

    $connector->{safe_cv} = Safe->new();
    $connector->{safe_cv}->share('$values');
    $connector->{safe_cv}->permit_only(':default');

    $connector->{uuid} = $connector->generate_token(length => 4) . ':' . $options{service_number};
    return $connector;
}

sub database_init_transaction {
    my ($self, %options) = @_;

    my $status = $self->{class_object_centreon}->{db_centreon}->transaction_mode(1);
    if ($status == -1) {
        $self->{logger}->writeLogError("$@");
        return -1;
    }
    return 0;
}

sub database_commit_transaction {
    my ($self, %options) = @_;
    
    my $status = $self->{class_object_centreon}->commit();
    if ($status == -1) {
        $self->{logger}->writeLogError("$@");
        return -1;
    }

    $self->{class_object_centreon}->transaction_mode(0);
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

sub send_email {
    my ($self, %options) = @_;

    my $messages = {};
    foreach my $journal (@{$self->{discovery}->{journal}}) {
        $messages->{ $journal->{rule_id } } = [] if (!defined($messages->{ $journal->{rule_id } }));
        push @{$messages->{ $journal->{rule_id } }}, $journal->{type} . " service '" . $journal->{service_name} . "' on host '" . $journal->{host_name} . "'.";
    }

    my $contact_send = {};
    foreach my $rule_id (keys %{$self->{discovery}->{rules}}) {
        next if (!defined($self->{discovery}->{rules}->{$rule_id}->{contact}));
        next if (!defined($messages->{$rule_id}));

        foreach my $contact_id (keys %{$self->{discovery}->{rules}->{$rule_id}->{contact}}) {
            next if (defined($contact_send->{$contact_id}));
            $contact_send->{$contact_id} = 1;

            my $body = [];
            foreach my $rule_id2 (keys %{$messages}) {
                if (defined($self->{discovery}->{rules}->{$rule_id2}->{contact}->{$contact_id})) {
                    push @$body, @{$messages->{$rule_id2}};
                }
            }

            if (scalar(@$body) > 0) {
                $self->{logger}->writeLogInfo("[autodiscovery] -servicediscovery- $self->{uuid} send email to '" . $contact_id .  "' (" . $self->{discovery}->{rules}->{$rule_id}->{contact}->{$contact_id}->{contact_email} . ")");

                my $smtp = Net::SMTP->new('localhost', Timeout => 15);
                if (!defined($smtp)) {
                    $self->{logger}->writeLogError("[autodiscovery] -servicediscovery- sent email error - " . $@);
                    next;
                }
                $smtp->mail($self->{mail_from});
                if (!$smtp->to($self->{discovery}->{rules}->{$rule_id}->{contact}->{$contact_id}->{contact_email})) {
                    $self->{logger}->writeLogError("[autodiscovery] -servicediscovery- sent email error - " . $smtp->message());
                    next;
                }

                $smtp->data();
                $smtp->datasend(
                    'Date: ' . strftime('%a, %d %b %Y %H:%M:%S %z', localtime(time())) . "\n" .
                    'From: ' . $self->{mail_from} . "\n" .
                    'To: ' . $self->{discovery}->{rules}->{$rule_id}->{contact}->{$contact_id}->{contact_email} . "\n" .
                    'Subject: ' . $self->{mail_subject} . "\n" .
                    "\n" .
                    join("\n", @$body) . "\n"
                );
                $smtp->dataend();
                $smtp->quit();
            }
        }
    }
}

sub restart_pollers {
    my ($self, %options) = @_;

    return if ($self->{discovery}->{no_generate_config} == 1);

    my $poller_ids = {};
    foreach my $poller_id (keys %{$self->{discovery}->{pollers_reload}}) {
        $self->{logger}->writeLogInfo("[autodiscovery] -servicediscovery- $self->{uuid} generate poller config '" . $poller_id . "'");
        $self->send_internal_action(
            action => 'COMMAND',
            token => $self->{discovery}->{token} . ':config',
            data => {
                content => [
                    {
                        command => $self->{tpapi_clapi}->get_applycfg_command(poller_id => $poller_id)
                    }
                ]
            }
        );
    }
}

sub audit_update {
    my ($self, %options) = @_;
    
    return if ($self->{discovery}->{audit_enable} != 1);

    my $query = 'INSERT INTO log_action (action_log_date, object_type, object_id, object_name, action_type, log_contact_id) VALUES (' . 
        time() . ', ' . $self->{class_object_centstorage}->quote(value => $options{object_type}) . ',' . 
        $self->{class_object_centstorage}->quote(value => $options{object_id}) . ',' . 
        $self->{class_object_centstorage}->quote(value => $options{object_name}) . ',' .
        $self->{class_object_centstorage}->quote(value => $options{action_type}) . ',' .
        $self->{class_object_centstorage}->quote(value => $options{contact_id}) .
        ')';
    my ($status, $sth) = $self->{class_object_centstorage}->custom_execute(request => $query);

    return if (!defined($options{fields}));

    my $action_log_id = $self->{class_object_centstorage}->{db_centreon}->last_insert_id();
    foreach (keys %{$options{fields}}) {
        $query = 'INSERT INTO log_action_modification (action_log_id, field_name, field_value) VALUES (' .
            $action_log_id . ', '.
            $self->{class_object_centstorage}->quote(value => $_) .  ', ' .
            $self->{class_object_centstorage}->quote(value => $options{fields}->{$_}) .
            ')';
        ($status) = $self->{class_object_centstorage}->custom_execute(request => $query);
        if ($status == -1) {
            return -1;
        }
    }
}

sub custom_variables {
    my ($self, %options) = @_;

    if (defined($options{rule}->{rule_variable_custom}) && $options{rule}->{rule_variable_custom} ne '') {
        local $SIG{__DIE__} = 'IGNORE';

        our $values = { attributes => $options{discovery_svc}->{attributes}, service_name => $options{discovery_svc}->{service_name} };
        $self->{safe_cv}->reval($options{rule}->{rule_variable_custom}, 1);
        if ($@) {
            $self->{logger}->writeLogError("$options{logger_pre_message} custom variable code execution problem: " . $@);
        } else {
            $options{discovery_svc}->{attributes} = $values->{attributes};
        }
    }
}

sub get_description {
    my ($self, %options) = @_;
    
    my $desc = $options{discovery_svc}->{service_name};
    if (defined($self->{discovery}->{rules}->{ $options{rule_id} }->{rule_scan_display_custom}) && $self->{discovery}->{rules}->{ $options{rule_id} }->{rule_scan_display_custom} ne '') {
        local $SIG{__DIE__} = 'IGNORE';

        our $description = $desc;
        our $values = { attributes => $options{discovery_svc}->{attributes}, service_name => $options{discovery_svc}->{service_name} };
        $self->{safe_display}->reval($self->{discovery}->{rules}->{ $options{rule_id} }->{rule_scan_display_custom}, 1);
        if ($@) {
            $self->{logger}->writeLogError("$options{logger_pre_message} [" . $options{discovery_svc}->{service_name} . "] custom description code execution problem: " . $@);
        } else {
            $desc = $description;
        }
    }

    return $desc;
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

sub update_service {
    my ($self, %options) = @_;
    my %query_update = ();
    my @journal = ();
    my @update_macros = ();
    my @insert_macros = ();
    
    if ($self->{discovery}->{is_manual} == 1) {
        $self->{discovery}->{manual}->{ $options{host_id} }->{rules}->{ $options{rule_id} }->{discovery}->{ $options{discovery_svc}->{service_name} } = { 
            type => 0,
            macros => {},
            description => $self->get_description(%options)
        };
    }

    return if ($self->{discovery}->{rules}->{ $options{rule_id} }->{rule_update} == 0);

    if ($options{service}->{template_id} != $self->{discovery}->{rules}->{ $options{rule_id} }->{service_template_model_id}) {
        $query_update{service_template_model_stm_id} = $self->{discovery}->{rules}->{ $options{rule_id} }->{service_template_model_id};
        push @journal, {
            host_name => $self->{discovery}->{hosts}->{ $options{host_id} }->{host_name},
            service_name => $options{discovery_svc}->{service_name},
            type => 'update',
            msg => 'template',
            rule_id => $options{rule_id}
        }; 
        $self->{logger}->writeLogInfo("$options{logger_pre_message} [" . $options{discovery_svc}->{service_name} . "] -> service update template");
        if ($self->{discovery}->{is_manual} == 1) {
            $self->{discovery}->{manual}->{ $options{host_id} }->{rules}->{ $options{rule_id} }->{discovery}->{ $options{discovery_svc}->{service_name} }->{service_template_model_stm_id} = $self->{discovery}->{rules}->{ $options{rule_id} }->{service_template_model_id};
        }
    }
    if ($options{service}->{activate} == '0') {
        $query_update{service_activate} = "'1'";
        push @journal, {
            host_name => $self->{discovery}->{hosts}->{ $options{host_id} }->{host_name},
            service_name => $options{discovery_svc}->{service_name},
            type => 'enable',
            rule_id => $options{rule_id}
        };
        $self->{logger}->writeLogInfo("$options{logger_pre_message} [" . $options{discovery_svc}->{service_name} . "] -> service enable");
    }

    foreach my $macro_name (keys %{$options{macros}}) {
        if (!defined($options{service}->{macros}->{'$_SERVICE' . $macro_name . '$'})) {
            push @insert_macros, {
                name => $macro_name,
                value => $options{macros}->{$macro_name}
            };
            if ($self->{discovery}->{is_manual} == 1) {
                $self->{discovery}->{manual}->{ $options{host_id} }->{rules}->{ $options{rule_id} }->{discovery}->{ $options{discovery_svc}->{service_name} }->{macros}->{$macro_name} = { value => $options{macros}->{$macro_name}, type => 1 };
            }
        } elsif ($options{service}->{macros}->{'$_SERVICE' . $macro_name . '$'} ne $options{macros}->{$macro_name})  {
            push @update_macros, {
                name => $macro_name,
                value => $options{macros}->{$macro_name}
            };
            if ($self->{discovery}->{is_manual} == 1) {
                $self->{discovery}->{manual}->{ $options{host_id} }->{rules}->{ $options{rule_id} }->{discovery}->{ $options{discovery_svc}->{service_name} }->{macros}->{$macro_name} = { value => $options{macros}->{$macro_name}, type => 0 };
            }
        }
    }

    if (scalar(@insert_macros) > 0 || scalar(@update_macros) > 0) {
        push @journal, {
            host_name => $self->{discovery}->{hosts}->{ $options{host_id} }->{host_name},
            service_name => $options{discovery_svc}->{service_name},
            type => 'update',
            msg => 'macros',
            rule_id => $options{rule_id}
        };
        $self->{logger}->writeLogInfo("$options{logger_pre_message} [" . $options{discovery_svc}->{service_name} . "] -> service update/insert macros");
    }

    return $options{service}->{id} if ($self->{discovery}->{dry_run} == 1 || scalar(@journal) == 0);

    return -1 if ($self->database_init_transaction() == -1);

    if (scalar(keys %query_update) > 0) {
        my $set = '';
        my $set_append = '';
        foreach (keys %query_update) {
            $set .= $set_append . $_ . ' = ' . $query_update{$_};
            $set_append = ', ';
        }
        my $query = 'UPDATE service SET ' . $set . ' WHERE service_id = ' . $options{service}->{id};
        my ($status) = $self->{class_object_centreon}->custom_execute(request => $query);
        if ($status == -1) {
            return $self->database_error_rollback(message => "$options{logger_pre_message} [" . $options{discovery_svc}->{service_name} . "] -> cannot update service");
        }
    }
    
    foreach (@update_macros) {
        my $query = 'UPDATE on_demand_macro_service SET svc_macro_value = ' . $self->{class_object_centreon}->quote(value => $_->{value}) . ' WHERE svc_svc_id = ' . $options{service}->{id} .  ' AND svc_macro_name = ' . $self->{class_object_centreon}->quote(value => '$_SERVICE' . $_->{name} . '$');
        my ($status) = $self->{class_object_centreon}->custom_execute(request => $query);
        if ($status == -1) {
            return $self->database_error_rollback(message => "$options{logger_pre_message} [" . $options{discovery_svc}->{service_name} . "] -> cannot update macro");
        }
    }
    foreach (@insert_macros) {
        my $query = 'INSERT on_demand_macro_service (svc_svc_id, svc_macro_name, svc_macro_value) VALUES (' . $options{service}->{id} .  ', ' . $self->{class_object_centreon}->quote(value => '$_SERVICE' . $_->{name} . '$') . ', ' . $self->{class_object_centreon}->quote(value => $_->{value}) . ')';
        my ($status) = $self->{class_object_centreon}->custom_execute(request => $query);
        if ($status == -1) {
            return $self->database_error_rollback(message => "$options{logger_pre_message} [" . $options{discovery_svc}->{service_name} . "] -> cannot insert macro");
        }
    }
    
    if ($self->link_service_autodisco(%options, service_id => $options{service}->{id}) == -1) {
        return $self->database_error_rollback(message => "$options{logger_pre_message} [" . $options{discovery_svc}->{service_name} . "] -> cannot link service to autodisco");
    }

    return -1 if ($self->database_commit_transaction() == -1);

    $self->{discovery}->{pollers_reload}->{ $options{poller_id} } = 1;
    push @{$self->{discovery}->{journal}}, @journal;

    if (defined($query_update{service_activate})) {
        $self->audit_update(
            object_type => 'service', 
            action_type => 'enable', 
            object_id => $options{service}->{id}, 
            object_name => $options{discovery_svc}->{service_name}, 
            contact_id => $self->{audit_user_id}
        );
    }
    if (defined($query_update{service_template_model_stm_id})) {
        $self->audit_update(
            object_type => 'service', 
            action_type => 'c', 
            object_id => $options{service}->{id}, 
            object_name => $options{discovery_svc}->{service_name}, 
            contact_id => $self->{audit_user_id},
            fields => { service_template_model_stm_id => $query_update{service_template_model_stm_id} }
        );
    }

    return $options{service}->{id};
}

sub create_service {
    my ($self, %options) = @_;
    
    if ($self->{discovery}->{is_manual} == 1) {
        $self->{discovery}->{manual}->{ $options{host_id} }->{rules}->{ $options{rule_id} }->{discovery}->{ $options{discovery_svc}->{service_name} } = { 
            type => 1, 
            service_template_model_stm_id => $self->{discovery}->{rules}->{ $options{rule_id} }->{service_template_model_id},
            macros => {},
            description => $self->get_description(%options)
        };
        foreach (keys %{$options{macros}}) {
            $self->{discovery}->{manual}->{ $options{host_id} }->{rules}->{ $options{rule_id} }->{discovery}->{ $options{discovery_svc}->{service_name} }->{macros}->{$_} = {
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

    $self->audit_update(
        object_type => 'service', 
        action_type => 'a', 
        object_id => $service_id, 
        object_name => $options{discovery_svc}->{service_name},
        contact_id => $self->{audit_user_id},
        fields => {
            service_template_model_id => $self->{discovery}->{rules}->{ $options{rule_id} }->{service_template_model_id}, 
            service_description => $options{discovery_svc}->{service_name}, 
            service_register => '1', 
            service_hPars => $options{host_id}
        }
    );

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
                type => 'created',
                rule_id => $options{rule_id}
            };
        }
    } else {
        $service_id = $self->update_service(%options);
    }
    
    return 0;
}

sub disable_services {
    my ($self, %options) = @_;
    
    return if ($self->{discovery}->{rules}->{ $options{rule_id} }->{rule_disable} != 1 || !defined($self->{discovery}->{rules}->{ $options{rule_id} }->{linked_services}->{ $options{host_id} }));
    foreach my $service (keys %{$self->{discovery}->{rules}->{ $options{rule_id} }->{linked_services}->{ $options{host_id} }}) {
        my $service_description = $self->{discovery}->{rules}->{ $options{rule_id} }->{linked_services}->{ $options{host_id} }->{$service}->{service_description};

        if (!defined($options{discovery_svc}->{discovered_services}->{$service_description}) && 
            $self->{discovery}->{rules}->{ $options{rule_id} }->{linked_services}->{ $options{host_id} }->{$service}->{service_activate} == 1) {
            $self->{logger}->writeLogInfo("$options{logger_pre_message} -> disable service '" . $service_description . "'");
            next if ($self->{discovery}->{dry_run} == 1);

            my $query = "UPDATE service SET service_activate = '0' WHERE service_id = " . $service;
            my ($status) = $self->{class_object_centreon}->custom_execute(request => $query);
            if ($status == -1) {
                $self->{logger}->writeLogInfo("$options{logger_pre_message} -> cannot disable service '" . $service_description . "'");
                next;
            }
            
            push @{$self->{discovery}->{journal}}, {
                host_name => $self->{discovery}->{hosts}->{ $options{host_id} }->{host_name},
                service_name => $service_description,
                type => 'disable',
                rule_id => $options{rule_id}
            }; 
            $self->{discovery}->{pollers_reload}->{ $options{poller_id} } = 1;
            $self->audit_update(
                object_type => 'service', 
                action_type => 'disable', 
                object_id => $service, 
                object_name => $service_description,
                contact_id => $self->{audit_user_id}
            );
        }
    }
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
        if ($self->{discovery}->{is_manual} == 1) {
            $self->{discovery}->{manual}->{ $options{host_id} }->{rules}->{ $options{rule_id} }->{failed} = 1;
            $self->{discovery}->{manual}->{ $options{host_id} }->{rules}->{ $options{rule_id} }->{message} = 'load xml issue';
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
        $self->custom_variables(
            discovery_svc => $discovery_svc,
            rule => $self->{discovery}->{rules}->{ $options{rule_id} },
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

    $self->disable_services(
        discovery_svc => $discovery_svc,
        rule_id => $options{rule_id},
        host_id => $options{host_id},
        poller_id => $options{poller_id},
        logger_pre_message => $logger_pre_message
    );
}

sub discoverylistener {
    my ($self, %options) = @_;

    return 0 if ($options{data}->{code} != GORGONE_MODULE_ACTION_COMMAND_RESULT && $options{data}->{code} != GORGONE_ACTION_FINISH_KO);

    if ($self->{discovery}->{is_manual} == 1) {
        $self->{discovery}->{manual}->{ $options{host_id} } = { rules => {} } if (!defined($self->{discovery}->{manual}->{ $options{host_id} }));
        $self->{discovery}->{manual}->{ $options{host_id} }->{rules}->{ $options{rule_id} } = { failed => 0, discovery => {} } if (!defined($self->{discovery}->{manual}->{ $options{host_id} }->{rules}->{ $options{rule_id} }));
    }

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
        } else {
            $self->{discovery}->{failed_discoveries}++;
            if ($self->{discovery}->{is_manual} == 1) {
                $self->{discovery}->{manual}->{ $options{host_id} }->{rules}->{ $options{rule_id} }->{failed} = 1;
                $self->{discovery}->{manual}->{ $options{host_id} }->{rules}->{ $options{rule_id} }->{message} = $options{data}->{data}->{message};
                $self->{discovery}->{manual}->{ $options{host_id} }->{rules}->{ $options{rule_id} }->{data} = $options{data}->{data};
            }
        }
    } elsif ($options{data}->{code} == GORGONE_ACTION_FINISH_KO) {
        if ($self->{discovery}->{is_manual} == 1) {
            $self->{discovery}->{manual}->{ $options{host_id} }->{rules}->{ $options{rule_id} }->{failed} = 1;
            $self->{discovery}->{manual}->{ $options{host_id} }->{rules}->{ $options{rule_id} }->{message} = $options{data}->{data}->{message};
        }
        $self->{discovery}->{failed_discoveries}++;
    } else {
        return 0;
    }

    $self->{service_current_commands_poller}->{ $self->{discovery}->{hosts}->{ $options{host_id} }->{poller_id} }--;
    $self->service_execute_commands();

    $self->{discovery}->{done_discoveries}++;
    my $progress = $self->{discovery}->{done_discoveries} * 100 / $self->{discovery}->{count_discoveries};
    my $div = int(int($progress) / 5);
    if ($div > $self->{discovery}->{progress_div}) {
        $self->{discovery}->{progress_div} = $div;
        $self->send_log(
            code => GORGONE_MODULE_CENTREON_AUTODISCO_SVC_PROGRESS,
            token => $self->{discovery}->{token},
            instant => 1,
            data => {
                message => 'current progress',
                complete => sprintf('%.2f', $progress) 
            }
        );
    }

    $self->{logger}->writeLogDebug("[autodiscovery] -servicediscovery- $self->{uuid} current count $self->{discovery}->{done_discoveries}/$self->{discovery}->{count_discoveries}");
    if ($self->{discovery}->{done_discoveries} == $self->{discovery}->{count_discoveries}) {
        $self->{logger}->writeLogDebug("[autodiscovery] -servicediscovery- $self->{uuid} discovery finished");
        $self->{finished} = 1;

        $self->send_log(
            code => GORGONE_ACTION_FINISH_OK,
            token => $self->{discovery}->{token},
            data => {
                message => 'discovery finished',
                failed_discoveries => $self->{discovery}->{failed_discoveries},
                count_discoveries => $self->{discovery}->{count_discoveries},
                journal => $self->{discovery}->{journal},
                manual => $self->{discovery}->{manual}
            }
        );

        if ($self->{discovery}->{is_manual} == 0) {
            $self->restart_pollers();
            $self->send_email();
        }
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
                        instant => 1,
                        content => [
                            {
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

    ($status, $message, my $audit_enable) = gorgone::modules::centreon::autodiscovery::services::resources::get_audit(
        class_object_centstorage => $self->{class_object_centstorage}
    );
    if ($status < 0) {
        $self->send_log_msg_error(token => $options{token}, subname => 'servicediscovery', number => $self->{uuid}, message => $message);
        return -1;
    }

    if (!defined($self->{tpapi_clapi}->get_username())) {
        $self->send_log_msg_error(token => $options{token}, subname => 'servicediscovery', number => $self->{uuid}, message => 'clapi ' . $self->{tpapi_clapi}->error());
        return -1;
    }
    ($status, $message, my $user_id) = gorgone::modules::centreon::autodiscovery::services::resources::get_audit_user_id(
        class_object_centreon => $self->{class_object_centreon},
        clapi_user => $self->{tpapi_clapi}->get_username()
    );
    if ($status < 0) {
        $self->send_log_msg_error(token => $options{token}, subname => 'servicediscovery', number => $self->{uuid}, message => $message);
        return -1;
    }
    $self->{audit_user_id} = $user_id;

    ################
    # get rules
    ################
    $self->{logger}->writeLogInfo("[autodiscovery] -servicediscovery- $self->{uuid} load rules configuration");
    
    ($status, $message, my $rules) = gorgone::modules::centreon::autodiscovery::services::resources::get_rules(
        class_object_centreon => $self->{class_object_centreon},
        filter_rules => $options{data}->{content}->{filter_rules},
        force_rule => (defined($options{data}->{content}->{force_rule}) && $options{data}->{content}->{force_rule} =~ /^1$/) ? 1 : 0
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
        $rules->{$rule_id}->{hosts} = $hosts->{pollers};
        $all_hosts = { %$all_hosts, %{$hosts->{infos}} };

        foreach (('rule_scan_display_custom', 'rule_variable_custom')) {
            if (defined($rules->{$rule_id}->{$_}) && $rules->{$rule_id}->{$_} ne '') {
                $rules->{$rule_id}->{$_} =~ s/\$([a-zA-Z_\-\.]*?)\$/\$values->{attributes}->{$1}/msg;
                $rules->{$rule_id}->{$_} =~ s/\@SERVICENAME\@/\$values->{service_name}/msg;
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
        failed_discoveries => 0,
        done_discoveries => 0,
        progress_div => 0,
        rules => $rules,
        manual => {},
        is_manual => (defined($options{data}->{content}->{manual}) && $options{data}->{content}->{manual} =~ /^1$/) ? 1 : 0,
        dry_run => (defined($options{data}->{content}->{dry_run}) && $options{data}->{content}->{dry_run} =~ /^1$/) ? 1 : 0,
        audit_enable => $audit_enable,
        no_generate_config => (defined($options{data}->{content}->{no_generate_config}) && $options{data}->{content}->{no_generate_config} =~ /^1$/) ? 1 : 0,
        options => defined($options{data}->{content}) ? $options{data}->{content} : {},
        hosts => $all_hosts,
        journal => [],
        pollers_reload => {}
    };

    $self->service_execute_commands();

    return 0;
}

1;
