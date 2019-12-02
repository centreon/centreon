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
use ZMQ::LibZMQ4;
use ZMQ::Constants qw(:all);

my $connectors = {};
my $callbacks = {};
my $sockets = {};

sub new {
    my ($class, %options) = @_;
    my $connector  = {};
    $connector->{logger} = $options{logger};
    $connector->{identity} = $options{identity};
    $connector->{cipher} = $options{cipher};
    $connector->{vector} = $options{vector};
    $connector->{symkey} = undef;
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
        $connector->{logger}->writeLogDebug('jwk thumbprint = ' . $connector->{client_pubkey}->export_key_jwk_thumbprint('SHA256'));
    }

    $connectors->{$options{identity}} = $connector;
    bless $connector, $class;
    return $connector;
}

sub init {
    my ($self, %options) = @_;
    
    $self->{handshake} = 0;
    $sockets->{$self->{identity}} = gorgone::standard::library::connect_com(
        zmq_type => 'ZMQ_DEALER', name => $self->{identity},
        logger => $self->{logger},
        type => $self->{target_type},
        path => $self->{target_path}
    );
    $callbacks->{$self->{identity}} = $options{callback} if (defined($options{callback}));
}

sub close {
    my ($self, %options) = @_;
    
    zmq_close($sockets->{$self->{identity}});
}

sub get_server_pubkey {
    my ($self, %options) = @_;

    zmq_sendmsg($sockets->{$self->{identity}}, '[GETPUBKEY]', ZMQ_DONTWAIT);
    zmq_poll([$self->get_poll()], 10000);
}

sub check_server_pubkey {
    my ($self, %options) = @_;

    if ($options{message} !~ /^\s*\[PUBKEY\]\s+\[(.*?)\]/) {
        $self->{logger}->writeLogError('cannot read pubbkey response from server: ' . $options{message}) if (defined($self->{logger}));
        $self->{verbose_last_message} = 'cannot read pubkey response from server';
        return 0;
    }

    my ($code, $verbose_message);
    my $server_pubkey_str = pack('H*', $1);
    ($code, $self->{server_pubkey}) = gorgone::standard::library::loadpubkey(
        pubkey_str => $server_pubkey_str,
        logger => $self->{logger},
        noquit => 1
    );

    if ($code == 0) {
        $self->{logger}->writeLogError('cannot load pubbkey') if (defined($self->{logger}));
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
        $self->{logger}->writeLogError("no ping response") if (defined($self->{logger}));
        $self->{ping_progress} = 0;
        zmq_close($sockets->{$self->{identity}});
        $self->init();
        push @{$options{poll}}, $self->get_poll();
        $status = 1;
    }
    
    push @{$options{poll}}, $self->get_poll();
    return $status;
}

sub get_poll {
    my ($self, %options) = @_;

    return {
        socket  => $sockets->{$self->{identity}},
        events  => ZMQ_POLLIN,
        callback => sub {
            event(identity => $self->{identity});
        }
    };
}

sub event {
    my (%options) = @_;

    # We have a response. So it's ok :)
    if ($connectors->{$options{identity}}->{ping_progress} == 1) {
        $connectors->{$options{identity}}->{ping_progress} = 0;
    }
    $connectors->{$options{identity}}->{ping_time} = time();
    while (1) {
        my $message = gorgone::standard::library::zmq_dealer_read_message(socket => $sockets->{$options{identity}});
        
        # in progress
        if ($connectors->{$options{identity}}->{handshake} == 0) {
            $connectors->{$options{identity}}->{handshake} = 1;
            if ($connectors->{$options{identity}}->check_server_pubkey(message => $message) == 0) {
                $connectors->{$options{identity}}->{handshake} = -1;
            }
        } elsif ($connectors->{$options{identity}}->{handshake} == 1) {
            my ($status, $verbose, $symkey, $hostname) = gorgone::standard::library::client_get_secret(
                privkey => $connectors->{$options{identity}}->{client_privkey},
                message => $message
            );
            if ($status == -1) {
                $connectors->{$options{identity}}->{handshake} = -1;
                $connectors->{$options{identity}}->{verbose_last_message} = $verbose;
                return ;
            }
            $connectors->{$options{identity}}->{symkey} = $symkey;
            $connectors->{$options{identity}}->{handshake} = 2;
            if (defined($connectors->{$options{identity}}->{logger})) {
                $connectors->{$options{identity}}->{logger}->writeLogInfo("Client connected successfuly to '" . $connectors->{$options{identity}}->{target_type} . '//' . $connectors->{$options{identity}}->{target_path});
            }
        } else {
            my ($status, $data) = gorgone::standard::library::uncrypt_message(
                message => $message, 
                cipher => $connectors->{$options{identity}}->{cipher}, 
                vector => $connectors->{$options{identity}}->{vector},
                symkey => $connectors->{$options{identity}}->{symkey}
            );
            
            if ($status == -1 || $data !~ /^\[(.+?)\]\s+\[(.*?)\]\s+(?:\[(.*?)\]\s*(.*)|(.*))$/m) {
                $connectors->{$options{identity}}->{handshake} = -1;
                $connectors->{$options{identity}}->{verbose_last_message} = 'uncrypt issue: ' . $data;
                return ;
            }
            
            if (defined($callbacks->{$options{identity}})) {
                $callbacks->{$options{identity}}->(identity => $options{identity}, data => $data);
            }
        }
        
        last unless (gorgone::standard::library::zmq_still_read(socket => $sockets->{$options{identity}}));
    }
}

sub send_message {
    my ($self, %options) = @_;

    if ($self->{handshake} == 0) {
        if (!defined($self->{server_pubkey})) {
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
            $self->{verbose_last_message} = 'crypt handshake issue';
            return (-1, $self->{verbose_last_message}); 
        }

        $self->{verbose_last_message} = 'Handshake timeout';
        zmq_sendmsg($sockets->{$self->{identity}}, $ciphertext, ZMQ_DONTWAIT);
        zmq_poll([$self->get_poll()], 10000);
    }
    
    if ($self->{handshake} < 2) {
        $self->{handshake} = 0;
        return (-1, $self->{verbose_last_message});
    }
    
    gorgone::standard::library::zmq_send_message(
        socket => $sockets->{$self->{identity}},
        cipher => $self->{cipher},
        symkey => $self->{symkey},
        vector => $self->{vector},
        %options
    );
    return 0;
}

1;
