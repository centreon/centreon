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

package gorgone::modules::core::cron::class;

use base qw(gorgone::class::module);

use strict;
use warnings;
use gorgone::standard::library;
use gorgone::standard::constants qw(:all);
use gorgone::standard::misc;
use Schedule::Cron;
use EV;

my %handlers = (TERM => {}, HUP => {});
my ($connector);

sub new {
    my ($class, %options) = @_;
    $connector = $class->SUPER::new(%options);
    bless $connector, $class;
    
    $connector->set_signal_handlers();
    return $connector;
}

sub set_signal_handlers {
    my $self = shift;

    $SIG{TERM} = \&class_handle_TERM;
    $handlers{TERM}->{$self} = sub { $self->handle_TERM() };
    $SIG{HUP} = \&class_handle_HUP;
    $handlers{HUP}->{$self} = sub { $self->handle_HUP() };
}

sub handle_HUP {
    my $self = shift;
    $self->{reload} = 0;
}

sub handle_TERM {
    my $self = shift;
    $self->{logger}->writeLogDebug("[cron] $$ Receiving order to stop...");
    $self->{stop} = 1;
}

sub class_handle_TERM {
    foreach (keys %{$handlers{TERM}}) {
        &{$handlers{TERM}->{$_}}();
    }
}

sub class_handle_HUP {
    foreach (keys %{$handlers{HUP}}) {
        &{$handlers{HUP}->{$_}}();
    }
}

sub action_getcron {
    my ($self, %options) = @_;
    
    $options{token} = $self->generate_token() if (!defined($options{token}));

    my $data;
    my $id = $options{data}->{variables}[0];
    my $parameter = $options{data}->{variables}[1];
    if (defined($id) && $id ne '') {
        if (defined($parameter) && $parameter =~ /^status$/) {
            $self->{logger}->writeLogInfo("[cron] Get logs results for definition '" . $id . "'");
            $self->send_internal_action({
                action => 'GETLOG',
                token => $options{token},
                data => {
                    token => $id,
                    ctime => $options{data}->{parameters}->{ctime},
                    etime => $options{data}->{parameters}->{etime},
                    limit => $options{data}->{parameters}->{limit},
                    code => $options{data}->{parameters}->{code}
                }
            });
            my $rev = zmq_poll($connector->{poll}, 5000);
            $data = $connector->{ack}->{data}->{data}->{result};
        } else {
            my $idx;
            eval {
                $idx = $self->{cron}->check_entry($id);
            };
            if ($@) {
                $self->{logger}->writeLogError("[cron] Cron get failed to retrieve entry index");
                $self->send_log(
                    code => GORGONE_ACTION_FINISH_KO,
                    token => $options{token},
                    data => { message => 'failed to retrieve entry index' }
                );
                return 1;
            }
            if (!defined($idx)) {
                $self->{logger}->writeLogError("[cron] Cron get failed no entry found for id");
                $self->send_log(
                    code => GORGONE_ACTION_FINISH_KO,
                    token => $options{token},
                    data => { message => 'no entry found for id' }
                );
                return 1;
            }

            eval {
                my $result = $self->{cron}->get_entry($idx);
                push @{$data}, { %{$result->{args}[1]->{definition}} } if (defined($result->{args}[1]->{definition}));
            };
            if ($@) {
                $self->{logger}->writeLogError("[cron] Cron get failed");
                $self->send_log(
                    code => GORGONE_ACTION_FINISH_KO,
                    token => $options{token},
                    data => { message => 'get failed:' . $@ }
                );
                return 1;
            }
        }
    } else {
        eval {
            my @results = $self->{cron}->list_entries();
            foreach my $cron (@results) {
                push @{$data}, { %{$cron->{args}[1]->{definition}} };
            }
        };
        if ($@) {
            $self->{logger}->writeLogError("[cron] Cron get failed");
            $self->send_log(
                code => GORGONE_ACTION_FINISH_KO,
                token => $options{token},
                data => { message => 'get failed:' . $@ }
            );
            return 1;
        }
    }

    $self->send_log(
        code => GORGONE_ACTION_FINISH_OK,
        token => $options{token},
        data => $data
    );
    return 0;
}

sub action_addcron {
    my ($self, %options) = @_;
    
    $options{token} = $self->generate_token() if (!defined($options{token}));

    $self->{logger}->writeLogDebug("[cron] Cron add start");

    foreach my $definition (@{$options{data}->{content}}) {
        if (!defined($definition->{timespec}) || $definition->{timespec} eq '' ||
            !defined($definition->{action}) || $definition->{action} eq '' ||
            !defined($definition->{id}) || $definition->{id} eq '') {
            $self->{logger}->writeLogError("[cron] Cron add missing arguments");
            $self->send_log(
                code => GORGONE_ACTION_FINISH_KO,
                token => $options{token},
                data => { message => 'missing arguments' }
            );
            return 1;
        }
    }
    
    eval {
        foreach my $definition (@{$options{data}->{content}}) {
            my $idx = $self->{cron}->check_entry($definition->{id});
            if (defined($idx)) {
                $self->send_log(
                    code => GORGONE_ACTION_FINISH_KO,
                    token => $options{token},
                    data => { message => "id '" . $definition->{id} . "' already exists" }
                );
                next;
            }
            $self->{logger}->writeLogInfo("[cron] Adding cron definition '" . $definition->{id} . "'");
            $self->{cron}->add_entry(
                $definition->{timespec},
                $definition->{id},
                {
                    connector => $connector,
                    socket => $connector->{internal_socket},
                    logger => $self->{logger},
                    definition => $definition
                }
            );
        }
    };
    if ($@) {
        $self->{logger}->writeLogError("[cron] Cron add failed");
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => 'add failed:' . $@ }
        );
        return 1;
    }

    $self->{logger}->writeLogDebug("[cron] Cron add finish");
    $self->send_log(
        code => GORGONE_ACTION_FINISH_OK,
        token => $options{token},
        data => { message => 'add succeed' }
    );
    return 0;
}

sub action_updatecron {
    my ($self, %options) = @_;
    
    $options{token} = $self->generate_token() if (!defined($options{token}));

    $self->{logger}->writeLogDebug("[cron] Cron update start");
    
    my $id = $options{data}->{variables}[0];
    if (!defined($id)) {
        $self->{logger}->writeLogError("[cron] Cron update missing id");
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => 'missing id' }
        );
        return 1;
    }

    my $idx;
    eval {
        $idx = $self->{cron}->check_entry($id);
    };
    if ($@) {
        $self->{logger}->writeLogError("[cron] Cron update failed to retrieve entry index");
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => 'failed to retrieve entry index' }
        );
        return 1;
    }
    if (!defined($idx)) {
        $self->{logger}->writeLogError("[cron] Cron update failed no entry found for id");
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => 'no entry found for id' }
        );
        return 1;
    }
    
    my $definition = $options{data}->{content};
    if ((!defined($definition->{timespec}) || $definition->{timespec} eq '') &&
        (!defined($definition->{command_line}) || $definition->{command_line} eq '')) {
        $self->{logger}->writeLogError("[cron] Cron update missing arguments");
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => 'missing arguments' }
        );
        return 1;
    }
    
    eval {
        my $entry = $self->{cron}->get_entry($idx);
        $entry->{time} = $definition->{timespec};
        $entry->{args}[1]->{definition}->{timespec} = $definition->{timespec}
            if (defined($definition->{timespec}));
        $entry->{args}[1]->{definition}->{command_line} = $definition->{command_line}
            if (defined($definition->{command_line}));
        $self->{cron}->update_entry($idx, $entry);
    };
    if ($@) {
        $self->{logger}->writeLogError("[cron] Cron update failed");
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => 'update failed:' . $@ }
        );
        return 1;
    }

    $self->{logger}->writeLogDebug("[cron] Cron update succeed");
    $self->send_log(
        code => GORGONE_ACTION_FINISH_OK,
        token => $options{token},
        data => { message => 'update succeed' }
    );
    return 0;
}

sub action_deletecron {
    my ($self, %options) = @_;
    
    $options{token} = $self->generate_token() if (!defined($options{token}));

    $self->{logger}->writeLogDebug("[cron] Cron delete start");
    
    my $id = $options{data}->{variables}->[0];
    if (!defined($id) || $id eq '') {
        $self->{logger}->writeLogError("[cron] Cron delete missing id");
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => 'missing id' }
        );
        return 1;
    }

    my $idx;
    eval {
        $idx = $self->{cron}->check_entry($id);
    };
    if ($@) {
        $self->{logger}->writeLogError("[cron] Cron delete failed to retrieve entry index");
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => 'failed to retrieve entry index' }
        );
        return 1;
    }
    if (!defined($idx)) {
        $self->{logger}->writeLogError("[cron] Cron delete failed no entry found for id");
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => 'no entry found for id' }
        );
        return 1;
    }
    
    eval {
        $self->{cron}->delete_entry($idx);
    };
    if ($@) {
        $self->{logger}->writeLogError("[cron] Cron delete failed");
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => 'delete failed:' . $@ }
        );
        return 1;
    }

    $self->{logger}->writeLogDebug("[cron] Cron delete finish");
    $self->send_log(
        code => GORGONE_ACTION_FINISH_OK,
        token => $options{token},
        data => { message => 'delete succeed' }
    );
    return 0;
}

sub event {
    while (1) {
        my ($message) = $connector->read_message(); 
        last if (!defined($message));

        $connector->{logger}->writeLogDebug("[cron] Event: $message");
        if ($message =~ /^\[ACK\]\s+\[(.*?)\]\s+(.*)$/m) {
            my $token = $1;
            my ($rv, $data) = $connector->json_decode(argument => $2, token => $token);
            next if ($rv);

            $connector->{ack} = {
                token => $token,
                data => $data
            };
        } else {
            $message =~ /^\[(.*?)\]\s+\[(.*?)\]\s+\[.*?\]\s+(.*)$/m;
            if ((my $method = $connector->can('action_' . lc($1)))) {
                $message =~ /^\[(.*?)\]\s+\[(.*?)\]\s+\[.*?\]\s+(.*)$/m;
                my ($action, $token) = ($1, $2);
                my ($rv, $data) = $connector->json_decode(argument => $3, token => $token);
                next if ($rv);

                $method->($connector, token => $token, data => $data);
            }
        }        
    }
}

sub stop_ev {
    EV::break();
}

sub cron_sleep {
    EV::timer(1, 0, \&stop_ev);
    EV::run();

    if ($connector->{stop} == 1) {
        $connector->{logger}->writeLogInfo("[cron] $$ has quit");
        exit(0);
    }
}

sub dispatcher {
    my ($id, $options) = @_;

    $options->{logger}->writeLogInfo("[cron] Launching job '" . $id . "'");

    my $token = (defined($options->{definition}->{keep_token})) && $options->{definition}->{keep_token} =~ /true|1/i
        ? $options->{definition}->{id} : undef;

    $options->{connector}->send_internal_action({
        socket => $options->{socket},
        token => $token,
        action => $options->{definition}->{action},
        target => $options->{definition}->{target},
        data => {
            content => $options->{definition}->{parameters}
        },
        json_encode => 1
    });
 
    EV::timer(5, 0, \&stop_ev);
    EV::run();
}

sub run {
    my ($self, %options) = @_;

    # Connect internal
    $connector->{internal_socket} = gorgone::standard::library::connect_com(
        context => $connector->{zmq_context},
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgone-cron',
        logger => $self->{logger},
        type => $self->get_core_config(name => 'internal_com_type'),
        path => $self->get_core_config(name => 'internal_com_path')
    );
    $connector->send_internal_action({
        action => 'CRONREADY',
        data => {}
    });

    # need at least one cron to get sleep working
    push @{$self->{config}->{cron}}, {
        id => "default",
        timespec => "0 0 * * *",
        action => "INFORMATION",
        parameters => {}
    };

    $self->{cron} = new Schedule::Cron(\&dispatcher, nostatus => 1, nofork => 1, catch => 1);

    foreach my $definition (@{$self->{config}->{cron}}) {
        $self->{cron}->add_entry(
            $definition->{timespec},
            $definition->{id},
            {
                connector => $connector,
                socket => $connector->{internal_socket},
                logger => $self->{logger},
                definition => $definition
            }
        );
    }

    EV::io($connector->{internal_socket}->get_fd(), EV::READ|EV::WRITE, \&event);

    $self->{cron}->run(sleep => \&cron_sleep);

    exit(0);
}

1;
