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

package gorgone::class::clientzmq;

use strict;
use warnings;
use gorgone::standard::library;
use gorgone::standard::misc;
use Crypt::Mode::CBC;
use MIME::Base64;
use Scalar::Util;
use ZMQ::FFI qw(ZMQ_DONTWAIT ZMQ_POLLIN);
use EV;

my $connectors = {};
my $callbacks = {};
my $sockets = {};

sub new {
    my ($class, %options) = @_;
    my $connector  = {};
    $connector->{context} = $options{context};
    $connector->{logger} = $options{logger};
    $connector->{identity} = $options{identity};
    $connector->{extra_identity} = gorgone::standard::library::generate_token(length => 12);
    $connector->{core_loop} = $options{core_loop};

    $connector->{verbose_last_message} = '';
    $connector->{config_core} = $options{config_core};

    if (defined($connector->{config_core}) && defined($connector->{config_core}->{fingerprint_mgr}->{package})) {
        my ($code, $class_mgr) = gorgone::standard::misc::mymodule_load(
            logger => $connector->{logger},
            module => $connector->{config_core}->{fingerprint_mgr}->{package}, 
            error_msg => "Cannot load module $connector->{config_core}->{fingerprint_mgr}->{package}"
        );
        if ($code == 0) {
            $connector->{fingerprint_mgr} = $class_mgr->new(
                logger => $connector->{logger},
                config => $connector->{config_core}->{fingerprint_mgr},
                config_core => $connector->{config_core}
            );
        }
    }

    if (defined($options{server_pubkey}) && $options{server_pubkey} ne '') {
        (undef, $connector->{server_pubkey}) = gorgone::standard::library::loadpubkey(
            pubkey => $options{server_pubkey},
            logger => $options{logger}
        );
    }
    (undef, $connector->{client_pubkey}) = gorgone::standard::library::loadpubkey(
        pubkey => $options{client_pubkey},
        logger => $options{logger}
    );
    (undef, $connector->{client_privkey}) = gorgone::standard::library::loadprivkey(
        privkey => $options{client_privkey},
        logger => $options{logger}
    );
    $connector->{target_type} = $options{target_type};
    $connector->{target_path} = $options{target_path};
    $connector->{ping} = defined($options{ping}) ? $options{ping} : -1;
    $connector->{ping_timeout} = defined($options{ping_timeout}) ? $options{ping_timeout} : 30;
    $connector->{ping_progress} = 0; 
    $connector->{ping_time} = time();
    $connector->{ping_timeout_time} = time();

    if (defined($connector->{logger}) && $connector->{logger}->is_debug()) {
        $connector->{logger}->writeLogDebug('[core] JWK thumbprint = ' . $connector->{client_pubkey}->export_key_jwk_thumbprint('SHA256'));
    }

    $connectors->{ $options{identity} } = $connector;
    bless $connector, $class;
    return $connector;
}

sub init {
    my ($self, %options) = @_;

    $self->{handshake} = 0;
    $sockets->{ $self->{identity} } = gorgone::standard::library::connect_com(
        context => $self->{context},
        zmq_type => 'ZMQ_DEALER',
        name => $self->{identity} . '-' .  $self->{extra_identity},
        logger => $self->{logger},
        type => $self->{target_type},
        path => $self->{target_path},
        zmq_ipv6 => $self->{config_core}->{ipv6}
    );
    $callbacks->{ $self->{identity} } = $options{callback} if (defined($options{callback}));
}

sub close {
    my ($self, %options) = @_;
    
    $sockets->{ $self->{identity} }->close();
}

sub get_connect_identity {
    my ($self, %options) = @_;

    return $self->{identity} . '-' .  $self->{extra_identity};
}

sub get_server_pubkey {
    my ($self, %options) = @_;

    $sockets->{ $self->{identity} }->send('[GETPUBKEY]', ZMQ_DONTWAIT);
    $self->event(identity => $self->{identity});

    my $w1 = $self->{connect_loop}->timer(
        10,
        0, 
        sub {
            $self->{connect_loop}->break();
        }
    );
    $self->{connect_loop}->run();
}

sub read_key_protocol {
    my ($self, %options) = @_;

    $self->{logger}->writeLogDebug('[clientzmq] ' . $self->{identity} . ' - read key protocol: ' . $options{text});

    return (-1, 'Wrong protocol') if ($options{text} !~ /^\[KEY\]\s+(.*)$/);

    my $data = gorgone::standard::library::json_decode(module => 'clientzmq', data => $1, logger => $self->{logger});
    return (-1, 'Wrong protocol') if (!defined($data));

    return (-1, 'Wrong protocol') if (
        !defined($data->{hostname}) ||
        !defined($data->{key}) || $data->{key} eq '' ||
        !defined($data->{cipher}) || $data->{cipher} eq '' ||
        !defined($data->{iv}) || $data->{iv} eq '' ||
        !defined($data->{padding}) || $data->{padding} eq ''
    );

    $self->{key} = pack('H*', $data->{key});
    $self->{iv} = pack('H*', $data->{iv});
    $self->{cipher} = $data->{cipher};
    $self->{padding} = $data->{padding};

    $self->{crypt_mode} = Crypt::Mode::CBC->new(
        $self->{cipher},
        $self->{padding}
    );

    return (0, 'ok');
}

sub decrypt_message {
    my ($self, %options) = @_;

    my $plaintext;
    eval {
        $plaintext = $self->{crypt_mode}->decrypt(
            MIME::Base64::decode_base64($options{message}),
            $self->{key},
            $self->{iv}
        );
    };
    if ($@) {
        $self->{logger}->writeLogError("[clientzmq] $self->{identity} - decrypt message issue: " .  $@);
        return (-1, $@);
    }
    return (0, $plaintext);
}

sub client_get_secret {
    my ($self, %options) = @_;

    my $plaintext;
    eval {
        my $cryptedtext = MIME::Base64::decode_base64($options{message});
        $plaintext = $self->{client_privkey}->decrypt($cryptedtext, 'v1.5');
    };
    if ($@) {
        return (-1, "Decoding issue: $@");
    }

    return $self->read_key_protocol(text => $plaintext);
}

sub check_server_pubkey {
    my ($self, %options) = @_;

    $self->{logger}->writeLogDebug("[clientzmq] $self->{identity} - get_server_pubkey check [1]");

    if ($options{message} !~ /^\s*\[PUBKEY\]\s+\[(.*?)\]/) {
        $self->{logger}->writeLogError('[clientzmq] ' . $self->{identity} . ' - cannot read pubbkey response from server: ' . $options{message}) if (defined($self->{logger}));
        $self->{verbose_last_message} = 'cannot read pubkey response from server';
        return 0;
    }

    my ($code, $verbose_message);
    my $server_pubkey_str = MIME::Base64::decode_base64($1);
    ($code, $self->{server_pubkey}) = gorgone::standard::library::loadpubkey(
        pubkey_str => $server_pubkey_str,
        logger => $self->{logger},
        noquit => 1
    );

    if ($code == 0) {
        $self->{logger}->writeLogError('[clientzmq] ' . $self->{identity} . ' cannot load pubbkey') if (defined($self->{logger}));
        $self->{verbose_last_message} = 'cannot load pubkey';
        return 0;
    }

    # if not set, we are in 'always' mode
    if (defined($self->{fingerprint_mgr})) {
        my $thumbprint = $self->{server_pubkey}->export_key_jwk_thumbprint('SHA256');
        ($code, $verbose_message) = $self->{fingerprint_mgr}->check_fingerprint(
            target => $self->{target_type} . '://' . $self->{target_path},
            fingerprint => $thumbprint
        );
        if ($code == 0) {
            $self->{logger}->writeLogError($verbose_message) if (defined($self->{logger}));
            $self->{verbose_last_message} = $verbose_message;
            return 0;
        }
    }

    $self->{logger}->writeLogDebug("[clientzmq] $self->{identity} - get_server_pubkey ok [1]");

    return 1;
}

sub is_connected {
    my ($self, %options) = @_;
    
    # Should be connected (not 100% sure)
    if ($self->{handshake} == 2) {
        return (0, $self->{ping_time});
    }
    return -1;
}

# TODO PING
sub ping {
    my ($self, %options) = @_;
    my $status = 0;
    
    if ($self->{ping} > 0 && $self->{ping_progress} == 0 && 
        time() - $self->{ping_time} > $self->{ping}) {
        $self->{ping_progress} = 1;
        $self->{ping_timeout_time} = time();
        my $action = defined($options{action}) ? $options{action} : 'PING';
        $self->send_message(action => $action, data => $options{data}, json_encode => $options{json_encode});
        $status = 1;
    }

    if ($self->{ping_progress} == 1 && 
        time() - $self->{ping_timeout_time} > $self->{ping_timeout}) {
        $self->{logger}->writeLogError("[clientzmq] No ping response") if (defined($self->{logger}));
        $self->{ping_progress} = 0;
        # we delete the old one
        for (my $i = 0; $i < scalar(@{$options{poll}}); $i++) {
            if (Scalar::Util::refaddr($sockets->{$self->{identity}}) eq Scalar::Util::refaddr($options{poll}->[$i]->{socket})) {
                splice @{$options{poll}}, $i, 1;
                last;
            }
        }
        $sockets->{ $self->{identity} }->close();

        $self->init();
        #push @{$options{poll}}, $self->get_poll();
        $status = 1;
    }
    
    return $status;
}

sub add_watcher {
    my ($self, %options) = @_;

    $self->{core_watcher} = $self->{core_loop}->io(
        $sockets->{ $self->{identity} }->get_fd(),
        EV::READ,
        sub {
            $self->event(identity => $self->{identity});
        }
    );
}

sub event {
    my ($self, %options) = @_;

    $connectors->{ $options{identity} }->{ping_time} = time();
    while ($sockets->{ $options{identity} }->has_pollin()) {
        # We have a response. So it's ok :)
        if ($connectors->{ $options{identity} }->{ping_progress} == 1) {
            $connectors->{ $options{identity} }->{ping_progress} = 0;
        }

        my ($rv, $message) = gorgone::standard::library::zmq_dealer_read_message(socket => $sockets->{ $options{identity} });
        last if ($rv);

        # in progress
        if ($connectors->{ $options{identity} }->{handshake} == 0) {
            $self->{connect_loop}->break();
            $connectors->{ $options{identity} }->{handshake} = 1;
            if ($connectors->{ $options{identity} }->check_server_pubkey(message => $message) == 0) {
                $connectors->{ $options{identity} }->{handshake} = -1;
                
            }
        } elsif ($connectors->{ $options{identity} }->{handshake} == 1) {
            $self->{connect_loop}->break();

            $self->{logger}->writeLogDebug("[clientzmq] $self->{identity} - client_get_secret recv [3]");
            my ($status, $verbose, $symkey, $hostname) = $connectors->{ $options{identity} }->client_get_secret(
                message => $message
            );
            if ($status == -1) {
                $self->{logger}->writeLogDebug("[clientzmq] $self->{identity} - client_get_secret $verbose [3]");
                $connectors->{ $options{identity} }->{handshake} = -1;
                $connectors->{ $options{identity} }->{verbose_last_message} = $verbose;
                return ;
            }
            $connectors->{ $options{identity} }->{handshake} = 2;
            if (defined($connectors->{ $options{identity} }->{logger})) {
                $connectors->{ $options{identity} }->{logger}->writeLogInfo(
                    "[clientzmq] $self->{identity} - Client connected successfully to '" . $connectors->{ $options{identity} }->{target_type} .
                    "://" . $connectors->{ $options{identity} }->{target_path} . "'"
                );
                $self->add_watcher();
            }
        } else {
            my ($rv, $data) = $connectors->{ $options{identity} }->decrypt_message(message => $message);

            if ($rv == -1 || $data !~ /^\[([a-zA-Z0-9:\-_]+?)\]\s+/) {
                $connectors->{ $options{identity} }->{handshake} = -1;
                $connectors->{ $options{identity} }->{verbose_last_message} = 'decrypt issue: ' . $data;
                return ;
            }

            if ($1 eq 'KEY') {
                ($rv) = $connectors->{ $options{identity} }->read_key_protocol(text => $data);
            } elsif (defined($callbacks->{$options{identity}})) {
                $callbacks->{$options{identity}}->(identity => $options{identity}, data => $data);
            }
        }
    }
}

sub zmq_send_message {
    my ($self, %options) = @_;

    my $message = $options{message};
    if (!defined($message)) {
        $message = gorgone::standard::library::build_protocol(%options);
    }

    eval {
        $message = $self->{crypt_mode}->encrypt(
            $message,
            $self->{key},
            $self->{iv}
        );
        $message = MIME::Base64::encode_base64($message, '');
    };
    if ($@) {
        $self->{logger}->writeLogError("[clientzmq] encrypt message issue: " .  $@);
        return undef;
    }

    $options{socket}->send($message, ZMQ_DONTWAIT);
    $self->event(identity => $self->{identity});
}

sub send_message {
    my ($self, %options) = @_;

    if ($self->{handshake} == 0) {
        $self->{connect_loop} = new EV::Loop();
        $self->{connect_watcher} = $self->{connect_loop}->io(
            $sockets->{ $self->{identity} }->get_fd(),
            EV::READ,
            sub {
                $self->event(identity => $self->{identity});
            }
        );

        if (!defined($self->{server_pubkey})) {
            $self->{logger}->writeLogDebug("[clientzmq] $self->{identity} - get_server_pubkey sent [1]");
            $self->get_server_pubkey();
        } else {
            $self->{handshake} = 1;
        }
    }

    if ($self->{handshake} == 1) {
        my ($status, $ciphertext) = gorgone::standard::library::client_helo_encrypt(
            identity => $self->{identity},
            server_pubkey => $self->{server_pubkey},
            client_pubkey => $self->{client_pubkey},
        );
        if ($status == -1) {
            $self->{logger}->writeLogDebug("[clientzmq] $self->{identity} - client_helo crypt handshake issue [2]");
            $self->{verbose_last_message} = 'crypt handshake issue';
            return (-1, $self->{verbose_last_message}); 
        }

        $self->{logger}->writeLogDebug("[clientzmq] $self->{identity} - client_helo sent [2]");

        $self->{verbose_last_message} = 'Handshake timeout';
        $sockets->{ $self->{identity} }->send($ciphertext, ZMQ_DONTWAIT);
        $self->event(identity => $self->{identity});

        my $w1 = $self->{connect_loop}->timer(
            10,
            0,
            sub { $self->{connect_loop}->break(); }
        );
        $self->{connect_loop}->run();
    }

    undef $self->{connect_loop} if (defined($self->{connect_loop}));

    if ($self->{handshake} < 2) {
        $self->{handshake} = 0;
        return (-1, $self->{verbose_last_message});
    }

    $self->zmq_send_message(
        socket => $sockets->{ $self->{identity} },
        %options
    );

    return 0;
}

1;
