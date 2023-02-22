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

package gorgone::class::module;

use strict;
use warnings;

use gorgone::standard::constants qw(:all);
use gorgone::standard::library;
use gorgone::standard::misc;
use gorgone::class::tpapi;
use ZMQ::FFI qw(ZMQ_DONTWAIT ZMQ_POLLIN);
use JSON::XS;
use Crypt::Mode::CBC;
use Try::Tiny;
use EV;

my %handlers = (DIE => {});

sub new {
    my ($class, %options) = @_;
    my $self  = {};
    bless $self, $class;

    {
        local $SIG{__DIE__};
        $self->{zmq_context} = ZMQ::FFI->new();
    }

    $self->{internal_socket} = undef;
    $self->{module_id} = $options{module_id};
    $self->{container_id} = $options{container_id};
    $self->{container} = '';
    $self->{container} = ' container ' . $self->{container_id} . ':' if (defined($self->{container_id}));

    $self->{core_id} = $options{core_id};
    $self->{logger} = $options{logger};
    $self->{config} = $options{config};
    $self->{exit_timeout} = (defined($options{config}->{exit_timeout}) && $options{config}->{exit_timeout} =~ /(\d+)/) ? $1 : 30;
    $self->{config_core} = $options{config_core};
    $self->{config_db_centreon} = $options{config_db_centreon};
    $self->{config_db_centstorage} = $options{config_db_centstorage};
    $self->{stop} = 0;
    $self->{fork} = 0;

    $self->{internal_crypt} = { enabled => 0 };
    if ($self->get_core_config(name => 'internal_com_crypt') == 1) {
        $self->{cipher} = Crypt::Mode::CBC->new(
            $self->get_core_config(name => 'internal_com_cipher'),
            $self->get_core_config(name => 'internal_com_padding')
        );

        $self->{internal_crypt} = {
            enabled => 1,
            rotation => $self->get_core_config(name => 'internal_com_rotation'),
            cipher => $self->get_core_config(name => 'internal_com_cipher'),
            padding => $self->get_core_config(name => 'internal_com_padding'),
            iv => $self->get_core_config(name => 'internal_com_iv'),
            core_keys => [$self->get_core_config(name => 'internal_com_core_key'), $self->get_core_config(name => 'internal_com_core_oldkey')],
            identity_keys => $self->get_core_config(name => 'internal_com_identity_keys')
        };
    }

    $self->{tpapi} = gorgone::class::tpapi->new();
    $self->{tpapi}->load_configuration(configuration => $options{config_core}->{tpapi});

    $SIG{__DIE__} = \&class_handle_DIE;
    $handlers{DIE}->{$self} = sub { $self->handle_DIE($_[0]) };

    return $self;
}

sub class_handle_DIE {
    my ($msg) = @_;

    foreach (keys %{$handlers{DIE}}) {
        &{$handlers{DIE}->{$_}}($msg);
    }
}

sub handle_DIE {
    my ($self, $msg) = @_;

    $self->{logger}->writeLogError("[$self->{module_id}]$self->{container} Receiving DIE: $msg");
}

sub generate_token {
   my ($self, %options) = @_;

   return gorgone::standard::library::generate_token(length => $options{length});
}

sub set_fork {
    my ($self, %options) = @_;

    $self->{fork} = 1;
}

sub event {
    my ($self, %options) = @_;

    my $socket = defined($options{socket}) ? $options{socket} : $self->{internal_socket};
    while (my $events = gorgone::standard::library::zmq_events(socket => $socket)) {
        if ($events & ZMQ_POLLIN) {
            my ($message) = $self->read_message();
            next if (!defined($message));

            $self->{logger}->writeLogDebug("[$self->{module_id}]$self->{container} Event: $message");
            if ($message =~ /^\[(.*?)\]/) {
                if ((my $method = $self->can('action_' . lc($1)))) {
                    $message =~ /^\[(.*?)\]\s+\[(.*?)\]\s+\[.*?\]\s+(.*)$/m;
                    my ($action, $token) = ($1, $2);
                    my ($rv, $data) = $self->json_decode(argument => $3, token => $token);
                    next if ($rv);

                    $method->($self, token => $token, data => $data);
                }
            }
        } else {
            last;
        }
    }
}

sub get_core_config {
    my ($self, %options) = @_;

    return $self->{config_core}->{gorgonecore} if (!defined($options{name}));

    return $self->{config_core}->{gorgonecore}->{ $options{name} };
}

sub read_message {
    my ($self, %options) = @_;

    my ($rv, $message) = gorgone::standard::library::zmq_dealer_read_message(
        socket => defined($options{socket}) ? $options{socket} : $self->{internal_socket},
        frame => $options{frame}
    );
    return (undef, 1) if ($rv);
    if ($self->{internal_crypt}->{enabled} == 0) {
        if (defined($options{frame})) {
            return (undef, 0);
        }
        return ($message, 0);
    }

    foreach my $key (@{$self->{internal_crypt}->{core_keys}}) {
        next if (!defined($key));

        if (defined($options{frame})) {
            if ($options{frame}->decrypt({ cipher => $self->{cipher}, key => $key, iv => $self->{internal_crypt}->{iv} }) == 0) {
                return (undef, 0);
            }
        } else {
            my $plaintext;
            try {
                $plaintext = $self->{cipher}->decrypt($message, $key, $self->{internal_crypt}->{iv});
            };
            if (defined($plaintext) && $plaintext =~ /^\[[A-Za-z_\-]+?\]/) {
                $message = undef;
                return ($plaintext, 0);
            }
        }
    }

    if (defined($options{frame})) {
        $self->{logger}->writeLogError("[$self->{module_id}]$self->{container} decrypt issue: " . $options{frame}->getLastError());
    } else {
        $self->{logger}->writeLogError("[$self->{module_id}]$self->{container} decrypt issue: " . ($_ ? $_ : 'no message'));
    }
    return (undef, 1);
}

sub send_internal_key {
    my ($self, %options) = @_;

    my $message = gorgone::standard::library::build_protocol(
        action => 'SETMODULEKEY',
        data => { key => unpack('H*', $options{key}) },
        json_encode => 1
    );
    try {
        $message = $self->{cipher}->encrypt($message, $options{encrypt_key}, $self->{internal_crypt}->{iv});
    } catch {
        $self->{logger}->writeLogError("[$self->{module_id}]$self->{container} encrypt issue: $_");
        return -1;
    };

    $options{socket}->send($message, ZMQ_DONTWAIT);
    $self->event(socket => $options{socket});
    return 0;
}

sub send_internal_action {
    my ($self, $options) = (shift, shift);

    if (!defined($options->{message})) {
         $options->{message} = gorgone::standard::library::build_protocol(
            token => $options->{token},
            action => $options->{action},
            target => $options->{target},
            data => $options->{data},
            json_encode => defined($options->{data_noencode}) ? undef : 1
        );
    }

    my $socket = defined($options->{socket}) ? $options->{socket} : $self->{internal_socket};
    if ($self->{internal_crypt}->{enabled} == 1) {
        my $identity = gorgone::standard::library::zmq_get_routing_id(socket => $socket);

        my $key = $self->{internal_crypt}->{core_keys}->[0];
        if ($self->{fork} == 0) {
            if (!defined($self->{internal_crypt}->{identity_keys}->{$identity}) || 
                (time() - $self->{internal_crypt}->{identity_keys}->{$identity}->{ctime}) > ($self->{internal_crypt}->{rotation})) {
                my ($rv, $genkey) = gorgone::standard::library::generate_symkey(
                    keysize => $self->get_core_config(name => 'internal_com_keysize')
                );
                ($rv) = $self->send_internal_key(
                    socket => $socket,
                    key => $genkey,
                    encrypt_key => defined($self->{internal_crypt}->{identity_keys}->{$identity}) ?
                        $self->{internal_crypt}->{identity_keys}->{$identity}->{key} : $self->{internal_crypt}->{core_keys}->[0]
                );
                return undef if ($rv == -1);
                $self->{internal_crypt}->{identity_keys}->{$identity} = {
                    key => $genkey,
                    ctime => time()
                };
            }
            $key = $self->{internal_crypt}->{identity_keys}->{$identity}->{key};
        }

        try {
            $options->{message} = $self->{cipher}->encrypt($options->{message}, $key, $self->{internal_crypt}->{iv});
        } catch {
            $self->{logger}->writeLogError("[$self->{module_id}]$self->{container} encrypt issue: $_");
            return undef;
        };
    }

    $socket->send($options->{message}, ZMQ_DONTWAIT);
    $self->event(socket => $socket);
}

sub send_log_msg_error {
    my ($self, %options) = @_;

    return if (!defined($options{token}));

    $self->{logger}->writeLogError("[$self->{module_id}]$self->{container} -$options{subname}- $options{number} $options{message}");
    $self->send_internal_action({
        socket => (defined($options{socket})) ? $options{socket} : $self->{internal_socket},
        action => 'PUTLOG',
        token => $options{token},
        data => { code => GORGONE_ACTION_FINISH_KO, etime => time(), instant => $options{instant}, token => $options{token}, data => { message => $options{message} } },
        json_encode => 1
    });
}

sub send_log {
    my ($self, %options) = @_;

    return if (!defined($options{token}));

    return if (defined($options{logging}) && $options{logging} =~ /^(?:false|0)$/);

    $self->send_internal_action({
        socket => (defined($options{socket})) ? $options{socket} : $self->{internal_socket},
        action => 'PUTLOG',
        token => $options{token},
        data => { code => $options{code}, etime => time(), instant => $options{instant}, token => $options{token}, data => $options{data} },
        json_encode => 1
    });
}

sub json_encode {
    my ($self, %options) = @_;

    my $encoded_arguments;
    try {
        $encoded_arguments = JSON::XS->new->encode($options{argument});
    } catch {
        $self->{logger}->writeLogError("[$self->{module_id}]$self->{container} $options{method} - cannot encode json: $_");
        return 1;
    };

    return (0, $encoded_arguments);
}

sub json_decode {
    my ($self, %options) = @_;

    my $decoded_arguments;
    try {
        $decoded_arguments = JSON::XS->new->decode($options{argument});
    } catch {
        $self->{logger}->writeLogError("[$self->{module_id}]$self->{container} $options{method} - cannot decode json: $_");
        if (defined($options{token})) {
            $self->send_log(
                code => GORGONE_ACTION_FINISH_KO,
                token => $options{token},
                data => { message => 'cannot decode json' }
            );
        }
        return 1;
    };

    return (0, $decoded_arguments);
}

sub execute_shell_cmd {
    my ($self, %options) = @_;

    my $timeout = defined($options{timeout}) &&  $options{timeout} =~ /(\d+)/ ? $1 : 30;
    my ($lerror, $stdout, $exit_code) = gorgone::standard::misc::backtick(
        command => $options{cmd},
        logger => $self->{logger},
        timeout => $timeout,
        wait_exit => 1,
    );
    if ($lerror == -1 || ($exit_code >> 8) != 0) {
        $self->{logger}->writeLogError("[$self->{module_id}]$self->{container} command execution issue $options{cmd} : " . $stdout);
        return -1;
    }

    return 0;
}

sub change_macros {
    my ($self, %options) = @_;

    $options{template} =~ s/%\{(.*?)\}/$options{macros}->{$1}/g;
    if (defined($options{escape})) {
        $options{template} =~ s/([\Q$options{escape}\E])/\\$1/g;
    }
    return $options{template};
}

sub action_bcastlogger {
    my ($self, %options) = @_;

    my $data = $options{data};
    if (defined($options{frame})) {
        $data = $options{frame}->decodeData();
    }

    if (defined($data->{content}->{severity}) && $data->{content}->{severity} ne '') {
        if ($data->{content}->{severity} eq 'default') {
            $self->{logger}->set_default_severity();
        } else {
            $self->{logger}->severity($data->{content}->{severity});
        }
    }
}

sub action_bcastcorekey {
    my ($self, %options) = @_;

    return if ($self->{internal_crypt}->{enabled} == 0);

    my $data = $options{data};
    if (defined($options{frame})) {
        $data = $options{frame}->decodeData();
    }

    if (defined($data->{key})) {
        $self->{logger}->writeLogDebug("[$self->{module_id}]$self->{container} core key changed");
        $self->{internal_crypt}->{core_keys}->[1] = $self->{internal_crypt}->{core_keys}->[0];
        $self->{internal_crypt}->{core_keys}->[0] = pack('H*', $data->{key});
    }
}

1;
