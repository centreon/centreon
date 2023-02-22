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

package gorgone::standard::library;

use strict;
use warnings;
use gorgone::standard::constants qw(:all);
use ZMQ::FFI qw(ZMQ_DEALER ZMQ_ROUTER ZMQ_ROUTER_HANDOVER ZMQ_IPV6 ZMQ_TCP_KEEPALIVE 
    ZMQ_CONNECT_TIMEOUT ZMQ_DONTWAIT ZMQ_SNDMORE ZMQ_IDENTITY ZMQ_FD ZMQ_EVENTS
    ZMQ_LINGER ZMQ_SNDHWM ZMQ_RCVHWM ZMQ_RECONNECT_IVL);
use JSON::XS;
use File::Basename;
use Crypt::PK::RSA;
use Crypt::PRNG;
use Crypt::Mode::CBC;
use File::Path;
use File::Basename;
use MIME::Base64;
use Errno;
use Time::HiRes;
use Try::Tiny;
use YAML::XS;
use gorgone::class::frame;
$YAML::XS::Boolean = 'JSON::PP';
$YAML::XS::LoadBlessed = 1;

our $listener;
my %zmq_type = ('ZMQ_ROUTER' => ZMQ_ROUTER, 'ZMQ_DEALER' => ZMQ_DEALER);

sub read_config {
    my (%options) = @_;
    
    my $config;
    try {
        $config = YAML::XS::LoadFile($options{config_file});
    } catch {
        $options{logger}->writeLogError("[core] Parsing config file error:");
        $options{logger}->writeLogError($@);
        exit(1);
    };
    
    return $config;
}

#######################
# Handshake functions
#######################

sub generate_keys {
    my (%options) = @_;

    my ($privkey, $pubkey);
    try {
        my $pkrsa = Crypt::PK::RSA->new();
        $pkrsa->generate_key(256, 65537);
        $pubkey = $pkrsa->export_key_pem('public_x509');
        $privkey = $pkrsa->export_key_pem('private');
    } catch {
        $options{logger}->writeLogError("[core] Cannot generate server keys: $_");
        return 0;
    };

    return (1, $privkey, $pubkey);
}

sub loadpubkey {
    my (%options) = @_;
    my $quit = defined($options{noquit}) ? 0 : 1;
    my $string_key = '';

    if (defined($options{pubkey})) {
        if (!open FILE, "<" . $options{pubkey}) {
            $options{logger}->writeLogError("[core] Cannot read file '$options{pubkey}': $!") if (defined($options{logger}));
            exit(1) if ($quit);
            return 0;
        }
        while (<FILE>) {
            $string_key .= $_;
        }
        close FILE;
    } else {
        $string_key = $options{pubkey_str};
    }

    my $pubkey;
    try {
        $pubkey = Crypt::PK::RSA->new(\$string_key);
    } catch {
        $options{logger}->writeLogError("[core] Cannot load pubkey '$options{pubkey}': $_") if (defined($options{logger}));
        exit(1) if ($quit);
        return 0;
    };
    if ($pubkey->is_private()) {
        $options{logger}->writeLogError("[core] '$options{pubkey}' is not a public key") if (defined($options{logger}));
        exit(1) if ($quit);
        return 0;
    }

    return (1, $pubkey);
}

sub loadprivkey {
    my (%options) = @_;
    my $string_key = '';
    my $quit = defined($options{noquit}) ? 0 : 1;

    if (!open FILE, "<" . $options{privkey}) {
        $options{logger}->writeLogError("[core] Cannot read file '$options{privkey}': $!");
        exit(1) if ($quit);
        return 0;
    }
    while (<FILE>) {
        $string_key .= $_;
    }
    close FILE;

    my $privkey;
    try {
        $privkey = Crypt::PK::RSA->new(\$string_key);
    } catch {
        $options{logger}->writeLogError("[core] Cannot load privkey '$options{privkey}': $_");
        exit(1) if ($quit);
        return 0;
    };
    if (!$privkey->is_private()) {
        $options{logger}->writeLogError("[core] '$options{privkey}' is not a private key");
        exit(1) if ($quit);
        return 0;
    }

    return (1, $privkey);
}

sub zmq_core_pubkey_response {
    my (%options) = @_;
    
    if (defined($options{identity})) {
        zmq_sendmsg($options{socket}, pack('H*', $options{identity}), ZMQ_DONTWAIT | ZMQ_SNDMORE);
    }
    my $client_pubkey = $options{pubkey}->export_key_pem('public');
    my $msg = '[PUBKEY] [' . MIME::Base64::encode_base64($client_pubkey, '') . ']';

    zmq_sendmsg($options{socket}, $msg, ZMQ_DONTWAIT);
    return 0;
}

sub zmq_core_key_response {
    my (%options) = @_;
    
    if (defined($options{identity})) {
        zmq_sendmsg($options{socket}, pack('H*', $options{identity}), ZMQ_DONTWAIT | ZMQ_SNDMORE);
    }
    my $crypttext;
    try {
        $crypttext = $options{client_pubkey}->encrypt("[KEY] [$options{hostname}] [" . $options{symkey} . "]", 'v1.5');
    } catch {
        $options{logger}->writeLogError("[core] Encoding issue: $_");
        return -1;
    };

    zmq_sendmsg($options{socket}, MIME::Base64::encode_base64($crypttext, ''), ZMQ_DONTWAIT);
    return 0;
}

sub zmq_get_routing_id {
    my (%options) = @_;

    return $options{socket}->get_identity();
}

sub zmq_getfd {
    my (%options) = @_;

    return $options{socket}->get_fd();
}

sub zmq_events {
    my (%options) = @_;

    return $options{socket}->get(ZMQ_EVENTS, 'int');
}

sub generate_token {
    my (%options) = @_;

    my $length = (defined($options{length})) ? $options{length} : 64;
    my $token = Crypt::PRNG::random_bytes_hex($length);
    return $token;
}

sub generate_symkey {
    my (%options) = @_;
    
    my $random_key = Crypt::PRNG::random_bytes($options{keysize});
    return (0, $random_key);
}

sub client_helo_encrypt {
    my (%options) = @_;
    my $ciphertext;

    my $client_pubkey = $options{client_pubkey}->export_key_pem('public');
    try {
        $ciphertext = $options{server_pubkey}->encrypt('HELO', 'v1.5');
    } catch {
        return (-1, "Encoding issue: $_");
    };

    return (0, '[' . $options{identity} . '] [' . MIME::Base64::encode_base64($client_pubkey, '') . '] [' . MIME::Base64::encode_base64($ciphertext, '') . ']');
}

sub is_client_can_connect {
    my (%options) = @_;
    my $plaintext;

    if ($options{message} !~ /\[(.+)\]\s+\[(.+)\]\s+\[(.+)\]$/ms) {
        $options{logger}->writeLogError("[core] Decoding issue. Protocol not good: $options{message}");
        return -1;
    }

    my ($client, $client_pubkey_str, $cipher_text) = ($1, $2, $3);
    try {
        $plaintext = $options{privkey}->decrypt(MIME::Base64::decode_base64($cipher_text), 'v1.5');
    } catch {
        $options{logger}->writeLogError("[core] Decoding issue: $_");
        return -1;
    };
    if ($plaintext ne 'HELO') {
        $options{logger}->writeLogError("[core] Encrypted issue for HELO");
        return -1;
    }

    my ($client_pubkey);
    $client_pubkey_str = MIME::Base64::decode_base64($client_pubkey_str);
    try {
        $client_pubkey = Crypt::PK::RSA->new(\$client_pubkey_str);
    } catch {
        $options{logger}->writeLogError("[core] Cannot load client pubkey '$client_pubkey': $_");
        return -1;
    };

    my $is_authorized = 0;
    my $thumbprint = $client_pubkey->export_key_jwk_thumbprint('SHA256');
    if (defined($options{authorized_clients})) {
        foreach (@{$options{authorized_clients}}) {
            if ($_->{key} eq $thumbprint) {
                $is_authorized = 1;
                last;
            }
        }
    }
    
    if ($is_authorized == 0) {
        $options{logger}->writeLogError("[core] Client pubkey is not authorized. Thumbprint is '$thumbprint'");
        return -1;
    }

    $options{logger}->writeLogInfo("[core] Connection from $client");
    return (0, $client_pubkey);
}

#######################
# internal functions
#######################

sub addlistener {
    my (%options) = @_;

    my $data = $options{frame}->decodeData();
    if (!defined($data)) {
        return (GORGONE_ACTION_FINISH_KO, { message => 'request not well formatted' });
    }

    foreach (@$data) {
        $options{gorgone}->{listener}->add_listener(
            identity => $options{identity},
            event => $_->{event},
            target => $_->{target},
            token => $_->{token},
            log_pace => $_->{log_pace},
            timeout => $_->{timeout}
        );
    }

    return (GORGONE_ACTION_FINISH_OK, { action => 'addlistener', message => 'ok', data => $data });
}

sub getthumbprint {
    my (%options) = @_;

    if ($options{gorgone}->{keys_loaded} == 0) {
        return (GORGONE_ACTION_FINISH_KO, { action => 'getthumbprint', message => 'no public key loaded' }, 'GETTHUMBPRINT');
    }
    my $thumbprint = $options{gorgone}->{server_pubkey}->export_key_jwk_thumbprint('SHA256');
    return (GORGONE_ACTION_FINISH_OK, { action => 'getthumbprint', message => 'ok', data => { thumbprint => $thumbprint } }, 'GETTHUMBPRINT');
}

sub information {
    my (%options) = @_;
    
    my $data = {
        counters => $options{gorgone}->{counters},
        modules => $options{gorgone}->{modules_id},
        api_endpoints => $options{gorgone}->{api_endpoints}
    };
    return (GORGONE_ACTION_FINISH_OK, { action => 'information', message => 'ok', data => $data }, 'INFORMATION');
}

sub unloadmodule {
    my (%options) = @_;

    my $data = $options{frame}->decodeData();
    if (!defined($data)) {
        return (GORGONE_ACTION_FINISH_KO, { message => 'request not well formatted' });
    }

    if (defined($data->{content}->{package}) && defined($options{gorgone}->{modules_register}->{ $data->{content}->{package} })) {
        $options{gorgone}->{modules_register}->{ $data->{content}->{package} }->{gently}->(logger => $options{gorgone}->{logger});
        return (GORGONE_ACTION_BEGIN, { action => 'unloadmodule', message => "module '$data->{content}->{package}' unload in progress" }, 'UNLOADMODULE');
    }
    if (defined($data->{content}->{name}) &&
        defined($options{gorgone}->{modules_id}->{$data->{content}->{name}}) && 
        defined($options{gorgone}->{modules_register}->{ $options{gorgone}->{modules_id}->{$data->{content}->{name}} })) {
        $options{gorgone}->{modules_register}->{ $options{gorgone}->{modules_id}->{$data->{content}->{name}} }->{gently}->(logger => $options{gorgone}->{logger});
        return (GORGONE_ACTION_BEGIN, { action => 'unloadmodule', message => "module '$data->{content}->{name}' unload in progress" }, 'UNLOADMODULE');
    }

    return (GORGONE_ACTION_FINISH_KO, { action => 'unloadmodule', message => 'cannot find unload module' }, 'UNLOADMODULE');
}

sub loadmodule {
    my (%options) = @_;

    my $data = $options{frame}->decodeData();
    if (!defined($data)) {
        return (GORGONE_ACTION_FINISH_KO, { message => 'request not well formatted' });
    }

    if ($options{gorgone}->load_module(config_module => $data->{content})) {
        $options{gorgone}->{modules_register}->{ $data->{content}->{package} }->{init}->(
            id => $options{gorgone}->{id},
            logger => $options{gorgone}->{logger},
            poll => $options{gorgone}->{poll},
            external_socket => $options{gorgone}->{external_socket},
            internal_socket => $options{gorgone}->{internal_socket},
            dbh => $options{gorgone}->{db_gorgone},
            api_endpoints => $options{gorgone}->{api_endpoints}
        );
        return (GORGONE_ACTION_BEGIN, { action => 'loadmodule', message => "module '$data->{content}->{name}' is loaded" }, 'LOADMODULE');
    }

    return (GORGONE_ACTION_FINISH_KO, { action => 'loadmodule', message => "cannot load module '$data->{content}->{name}'" }, 'LOADMODULE');
}

sub synclogs {
    my (%options) = @_;

    my $data = $options{frame}->decodeData();
    if (!defined($data)) {
        return (GORGONE_ACTION_FINISH_KO, { message => 'request not well formatted' });
    }

    if (!defined($data->{data}->{id})) {
        return (GORGONE_ACTION_FINISH_KO, { action => 'synclog', message => 'please set id for synclog' });
    }

    if (defined($options{gorgone_config}->{gorgonecore}->{proxy_name}) && defined($options{gorgone}->{modules_id}->{$options{gorgone_config}->{gorgonecore}->{proxy_name}})) {
        my $name = $options{gorgone}->{modules_id}->{$options{gorgone_config}->{gorgonecore}->{proxy_name}};
        my $method;
        if (defined($name) && ($method = $name->can('synclog'))) {
            $method->(
                gorgone => $options{gorgone},
                dbh => $options{gorgone}->{db_gorgone},
                logger => $options{gorgone}->{logger}
            );
            return (GORGONE_ACTION_BEGIN, { action => 'synclog', message => 'synclog launched' });
        }
    }

    return (GORGONE_ACTION_FINISH_KO, { action => 'synclog', message => 'no proxy module' });
}

sub constatus {
    my (%options) = @_;

    if (defined($options{gorgone_config}->{gorgonecore}->{proxy_name}) && defined($options{gorgone}->{modules_id}->{$options{gorgone_config}->{gorgonecore}->{proxy_name}})) {
        my $name = $options{gorgone}->{modules_id}->{$options{gorgone_config}->{gorgonecore}->{proxy_name}};
        my $method;
        if (defined($name) && ($method = $name->can('get_constatus_result'))) {
            return (GORGONE_ACTION_FINISH_OK, { action => 'constatus', message => 'ok', data => $method->() }, 'CONSTATUS');
        }
    }
    
    return (GORGONE_ACTION_FINISH_KO, { action => 'constatus', message => 'cannot get value' }, 'CONSTATUS');
}

sub setmodulekey {
    my (%options) = @_;

    my $data = $options{frame}->decodeData();
    if (!defined($data)) {
        return (GORGONE_ACTION_FINISH_KO, { message => 'request not well formatted' });
    }

    if (!defined($data->{key})) {
        return (GORGONE_ACTION_FINISH_KO, { action => 'setmodulekey', message => 'please set key' });
    }

    my $id = pack('H*', $options{identity});
    $options{gorgone}->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_identity_keys}->{$id} = {
        key => pack('H*', $data->{key}),
        ctime => time()
    };

    $options{logger}->writeLogInfo('[core] module key ' . $id . ' changed');
    return (GORGONE_ACTION_FINISH_OK, { action => 'setmodulekey', message => 'setmodulekey changed' });
}

sub setcoreid {
    my (%options) = @_;

    if (defined($options{gorgone}->{config}->{configuration}->{gorgone}->{gorgonecore}->{id}) &&
        $options{gorgone}->{config}->{configuration}->{gorgone}->{gorgonecore}->{id} =~ /\d+/) {
        return (GORGONE_ACTION_FINISH_OK, { action => 'setcoreid', message => 'setcoreid unchanged, use config value' })
    }

    my $data = $options{frame}->decodeData();
    if (!defined($data)) {
        return (GORGONE_ACTION_FINISH_KO, { message => 'request not well formatted' });
    }

    if (!defined($data->{id})) {
        return (GORGONE_ACTION_FINISH_KO, { action => 'setcoreid', message => 'please set id for setcoreid' });
    }

    if (defined($options{gorgone_config}->{gorgonecore}->{proxy_name}) && defined($options{gorgone}->{modules_id}->{$options{gorgone_config}->{gorgonecore}->{proxy_name}})) {
        my $name = $options{gorgone}->{modules_id}->{$options{gorgone_config}->{gorgonecore}->{proxy_name}};
        my $method;
        if (defined($name) && ($method = $name->can('setcoreid'))) {
            $method->(dbh => $options{dbh}, core_id => $data->{id}, logger => $options{logger});
        }
    }

    $options{logger}->writeLogInfo('[core] Setcoreid changed ' .  $data->{id});
    $options{gorgone}->{id} = $data->{id};
    return (GORGONE_ACTION_FINISH_OK, { action => 'setcoreid', message => 'setcoreid changed' });
}

sub ping {
    my (%options) = @_;

    my $constatus = {};
    if (defined($options{gorgone_config}->{gorgonecore}->{proxy_name}) && defined($options{gorgone}->{modules_id}->{$options{gorgone_config}->{gorgonecore}->{proxy_name}})) {
        my $name = $options{gorgone}->{modules_id}->{$options{gorgone_config}->{gorgonecore}->{proxy_name}};
        my $method;
        if (defined($name) && ($method = $name->can('get_constatus_result'))) {
            $constatus = $method->();
        }
        if (defined($name) && ($method = $name->can('add_parent_ping'))) {
            $method->(router_type => $options{router_type}, identity => $options{identity}, logger => $options{logger});
        }
    }

    return (GORGONE_ACTION_BEGIN, { action => 'ping', message => 'ping ok', id => $options{id}, hostname => $options{gorgone}->{hostname}, data => $constatus }, 'PONG');
}

sub putlog {
    my (%options) = @_;

    my $data = $options{frame}->decodeData();
    if (!defined($data)) {
        return (GORGONE_ACTION_FINISH_KO, { message => 'request not well formatted' });
    }

    my $status = add_history({
        dbh => $options{gorgone}->{db_gorgone}, 
        etime => $data->{etime},
        token => $data->{token},
        instant => $data->{instant},
        data => json_encode(data => $data->{data}, logger => $options{logger}),
        code => $data->{code}
    });
    if ($status == -1) {
        return (GORGONE_ACTION_FINISH_KO, { message => 'database issue' });
    }
    return (GORGONE_ACTION_BEGIN, { message => 'message inserted' });
}

sub getlog {
    my (%options) = @_;

    my $data = $options{frame}->decodeData();
    if (!defined($data)) {
        return (GORGONE_ACTION_FINISH_KO, { message => 'request not well formatted' });
    }

    my %filters = ();
    my ($filter, $filter_append) = ('', '');
    my @bind_values = ();
    foreach ((['id', '>'], ['token', '='], ['ctime', '>'], ['etime', '>'], ['code', '='])) {
        if (defined($data->{$_->[0]}) && $data->{$_->[0]} ne '') {
            $filter .= $filter_append . $_->[0] . ' ' . $_->[1] . ' ?';
            $filter_append = ' AND ';
            push @bind_values, $data->{ $_->[0] };
        }
    }

    if ($filter eq '') {
        return (GORGONE_ACTION_FINISH_KO, { message => 'need at least one filter' });
    }

    my $query = "SELECT * FROM gorgone_history WHERE " . $filter;
    $query .= " ORDER BY id DESC LIMIT " . $data->{limit} if (defined($data->{limit}) && $data->{limit} ne '');

    my ($status, $sth) = $options{gorgone}->{db_gorgone}->query({ query => $query, bind_values => \@bind_values });
    if ($status == -1) {
        return (GORGONE_ACTION_FINISH_KO, { message => 'database issue' });
    }

    my @result;
    my $results = $sth->fetchall_hashref('id');
    foreach (sort keys %{$results}) {
        push @result, $results->{$_};
    }

    return (GORGONE_ACTION_BEGIN, { action => 'getlog', result => \@result, id => $options{gorgone}->{id} });
}

sub kill {
    my (%options) = @_;

    my $data = $options{frame}->decodeData();
    if (!defined($data)) {
        return (GORGONE_ACTION_FINISH_KO, { message => 'request not well formatted' });
    }

    if (defined($data->{content}->{package}) && defined($options{gorgone}->{modules_register}->{ $data->{content}->{package} })) {
        $options{gorgone}->{modules_register}->{ $data->{content}->{package} }->{kill}->(logger => $options{gorgone}->{logger});
        return (GORGONE_ACTION_FINISH_OK, { action => 'kill', message => "module '$data->{content}->{package}' kill in progress" });
    }
    if (defined($data->{content}->{name}) &&
        defined($options{gorgone}->{modules_id}->{ $data->{content}->{name} }) && 
        defined($options{gorgone}->{modules_register}->{ $options{gorgone}->{modules_id}->{ $data->{content}->{name} } })) {
        $options{gorgone}->{modules_register}->{ $options{gorgone}->{modules_id}->{ $data->{content}->{name} } }->{kill}->(logger => $options{gorgone}->{logger});
        return (GORGONE_ACTION_FINISH_OK, { action => 'kill', message => "module '$data->{content}->{name}' kill in progress" });
    }

    return (GORGONE_ACTION_FINISH_KO, { action => 'kill', message => 'cannot find module' });
}

#######################
# Database functions
#######################

sub update_identity_attrs {
    my (%options) = @_;

    my @fields = ();
    my @bind_values = ();
    foreach ('key', 'oldkey', 'iv', 'oldiv', 'ctime') {
        next if (!defined($options{$_}));

        if ($options{$_} eq 'NULL') {
            push @fields, "`$_` = NULL";
        } else {
            push @fields, "`$_` = ?";
            push @bind_values, $options{$_};
        }
    }
    push @bind_values, $options{identity}, $options{identity};

    my ($status, $sth) = $options{dbh}->query({
        query => "UPDATE gorgone_identity SET " . join(', ', @fields) .
            " WHERE `identity` = ? AND " .
            " `id` = (SELECT `id` FROM gorgone_identity WHERE `identity` = ? ORDER BY `id` DESC LIMIT 1)",
        bind_values => \@bind_values
    });

    return $status;
}

sub update_identity_mtime {
    my (%options) = @_;

    my ($status, $sth) = $options{dbh}->query({
        query => "UPDATE gorgone_identity SET `mtime` = ?" .
            " WHERE `identity` = ? AND " .
            " `id` = (SELECT `id` FROM gorgone_identity WHERE `identity` = ? ORDER BY `id` DESC LIMIT 1)",
        bind_values => [time(), $options{identity}, $options{identity}]
    });
    return $status;
}

sub add_identity {
    my (%options) = @_;

    my $time = time();
    my ($status, $sth) = $options{dbh}->query({
        query =>  "INSERT INTO gorgone_identity (`ctime`, `mtime`, `identity`, `key`, `iv`) VALUES (?, ?, ?, ?, ?)",
        bind_values => [$time, $time, $options{identity}, unpack('H*', $options{key}), unpack('H*', $options{iv})]
    });
    return $status;
}

sub add_history {
    my ($options) = (shift);

    if (defined($options->{data}) && defined($options->{json_encode})) {
        return -1 if (!($options->{data} = json_encode(data => $options->{data}, logger => $options->{logger})));
    }
    if (!defined($options->{ctime})) {
        $options->{ctime} = Time::HiRes::time();
    }
    if (!defined($options->{etime})) {
        $options->{etime} = time();
    }

    my $fields = '';
    my $placeholder = '';
    my $append = '';
    my @bind_values = ();
    foreach (('data', 'token', 'ctime', 'etime', 'code', 'instant')) {
        if (defined($options->{$_})) {
            $fields .= $append . $_;
            $placeholder .= $append . '?';
            $append = ', ';
            push @bind_values, $options->{$_};
        }
    }
    my ($status, $sth) = $options->{dbh}->query({
        query => "INSERT INTO gorgone_history ($fields) VALUES ($placeholder)",
        bind_values => \@bind_values
    });

    if (defined($options->{token}) && $options->{token} ne '') {
        $listener->event_log(
            {
                token => $options->{token},
                code => $options->{code},
                data => \$options->{data}
            }
        );
    }

    return $status;
}

#######################
# Misc functions
#######################

sub json_encode {
    my (%options) = @_;

    try {
        $options{data} = JSON::XS->new->encode($options{data});
    } catch {
        if (defined($options{logger})) {
            $options{logger}->writeLogError("[core] Cannot encode json data: $_");
        }
        return undef;
    };

    return $options{data};
}

sub json_decode {
    my (%options) = @_;

    try {
        $options{data} = JSON::XS->new->decode($options{data});
    } catch {
        if (defined($options{logger})) {
            $options{logger}->writeLogError("[$options{module}] Cannot decode json data: $_");
        }
        return undef;
    };

    return $options{data};
}

#######################
# Global ZMQ functions
#######################

sub connect_com {
    my (%options) = @_;

    my $socket = $options{context}->socket($zmq_type{$options{zmq_type}});
    if (!defined($socket)) {
        $options{logger}->writeLogError("Can't setup server: $!");
        exit(1);
    }
    $socket->die_on_error(0);

    $socket->set_identity($options{name});
    $socket->set(ZMQ_LINGER, 'int', defined($options{zmq_linger}) ? $options{zmq_linger} : 0); # 0 we discard
    $socket->set(ZMQ_SNDHWM, 'int', defined($options{zmq_sndhwm}) ? $options{zmq_sndhwm} : 0);
    $socket->set(ZMQ_RCVHWM, 'int', defined($options{zmq_rcvhwm}) ? $options{zmq_rcvhwm} : 0);
    $socket->set(ZMQ_RECONNECT_IVL, 'int', 1000);
    $socket->set(ZMQ_CONNECT_TIMEOUT, 'int', defined($options{zmq_connect_timeout}) ? $options{zmq_connect_timeout} : 30000);
    if ($options{zmq_type} eq 'ZMQ_ROUTER') {
        $socket->set(ZMQ_ROUTER_HANDOVER, 'int', defined($options{zmq_router_handover}) ? $options{zmq_router_handover} : 1);
    }
    if ($options{type} eq 'tcp') {
        $socket->set(ZMQ_TCP_KEEPALIVE, 'int', defined($options{zmq_tcp_keepalive}) ? $options{zmq_tcp_keepalive} : -1);
    }

    $socket->connect($options{type} . '://' . $options{path});
    return $socket;
}

sub create_com {
    my (%options) = @_;

    my $socket = $options{context}->socket($zmq_type{$options{zmq_type}});
    if (!defined($socket)) {
        $options{logger}->writeLogError("Can't setup server: $!");
        exit(1);
    }
    $socket->die_on_error(0);

    $socket->set_identity($options{name});
    $socket->set_linger(0);
    $socket->set(ZMQ_ROUTER_HANDOVER, 'int', defined($options{zmq_router_handover}) ? $options{zmq_router_handover} : 1);

    if ($options{type} eq 'tcp') {
        $socket->set(ZMQ_IPV6, 'int', defined($options{zmq_ipv6}) && $options{zmq_ipv6} =~ /true|1/i ? 1 : 0);
        $socket->set(ZMQ_TCP_KEEPALIVE, 'int', defined($options{zmq_tcp_keepalive}) ? $options{zmq_tcp_keepalive} : -1);

        $socket->bind('tcp://' . $options{path});
    } elsif ($options{type} eq 'ipc') {
        $socket->bind('ipc://' . $options{path});
        if ($socket->has_error) {
            $options{logger}->writeLogDebug("[core] Cannot bind IPC '$options{path}': $!");
            # try create dir
            $options{logger}->writeLogDebug("[core] Maybe directory not exist. We try to create it!!!");
            if (!mkdir(dirname($options{path}))) {
                $options{logger}->writeLogError("[core] Cannot create IPC file directory '$options{path}'");
                exit(1);
            }
            $socket->bind('ipc://' . $options{path});
            if ($socket->has_error) {
                $options{logger}->writeLogError("[core] Cannot bind IPC '$options{path}': " . $socket->last_strerror);
                exit(1);
            }
        }
    } else {
        $options{logger}->writeLogError("[core] ZMQ type '$options{type}' not managed");
        exit(1);
    }

    return $socket;
}

sub build_protocol {
    my (%options) = @_;
    my $data = $options{data};
    my $token = defined($options{token}) ? $options{token} : '';
    my $action = defined($options{action}) ? $options{action} : '';
    my $target = defined($options{target}) ? $options{target} : '';

    if (defined($options{raw_data_ref})) {
        return '[' . $action . '] [' . $token . '] [' . $target . '] ' . ${$options{raw_data_ref}};
    } elsif (defined($data)) {
        if (defined($options{json_encode})) {
            $data = json_encode(data => $data, logger => $options{logger});
        }
    } else {
        $data = json_encode(data => {}, logger => $options{logger});
    }

    return '[' . $action . '] [' . $token . '] [' . $target . '] ' . $data;
}

sub zmq_dealer_read_message {
    my (%options) = @_;

    my $data = $options{socket}->recv(ZMQ_DONTWAIT);
    if ($options{socket}->has_error) {
        return 1;
    }

    if (defined($options{frame})) {
        $options{frame}->setFrame(\$data);
        return 0;
    }

    return (0, $data);
}

sub zmq_read_message {
    my (%options) = @_;

    # Process all parts of the message
    my $identity = $options{socket}->recv(ZMQ_DONTWAIT);
    if ($options{socket}->has_error()) {
        return undef if ($options{socket}->last_errno == Errno::EAGAIN);

        $options{logger}->writeLogError("[core] zmq_recvmsg error: $!");
        return undef;
    }

    $identity = defined($identity) ? $identity  : 'undef';
    if ($identity !~ /^gorgone-/) {
        $options{logger}->writeLogError("[core] unknown identity: $identity");
        return undef;
    }

    my $data = $options{socket}->recv(ZMQ_DONTWAIT);
    if ($options{socket}->has_error()) {
        return undef if ($options{socket}->last_errno == Errno::EAGAIN);

        $options{logger}->writeLogError("[core] zmq_recvmsg error: $!");
        return undef;
    }

    my $frame = gorgone::class::frame->new();
    $frame->setFrame(\$data);

    return (unpack('H*', $identity), $frame);
}

sub zmq_still_read {
    my (%options) = @_;

    #return zmq_getsockopt($options{socket}, ZMQ_RCVMORE);        
}

sub add_zmq_pollin {
    my (%options) = @_;

    #push @{$options{poll}}, {
    #    socket  => $options{socket},
    #    events  => ZMQ_POLLIN,
    #    callback => $options{callback},
    #};
}

sub create_schema {
    my (%options) = @_;

    $options{logger}->writeLogInfo("[core] create schema $options{version}");
    my $schema = [
        q{
            PRAGMA encoding = "UTF-8"
        },
        q{
            CREATE TABLE `gorgone_information` (
              `key` varchar(1024) DEFAULT NULL,
              `value` varchar(1024) DEFAULT NULL
            );
        },
        qq{
            INSERT INTO gorgone_information (`key`, `value`) VALUES ('version', '$options{version}');
        },
        q{
            CREATE TABLE `gorgone_identity` (
              `id` INTEGER PRIMARY KEY,
              `ctime` int(11) DEFAULT NULL,
              `mtime` int(11) DEFAULT NULL,
              `identity` varchar(2048) DEFAULT NULL,
              `key` varchar(1024) DEFAULT NULL,
              `oldkey` varchar(1024) DEFAULT NULL,
              `iv` varchar(1024) DEFAULT NULL,
              `oldiv` varchar(1024) DEFAULT NULL,
              `parent` int(11) DEFAULT '0'
            );
        },
        q{
            CREATE INDEX idx_gorgone_identity ON gorgone_identity (identity);
        },
        q{
            CREATE INDEX idx_gorgone_parent ON gorgone_identity (parent);
        },
        q{
            CREATE TABLE `gorgone_history` (
              `id` INTEGER PRIMARY KEY,
              `token` varchar(2048) DEFAULT NULL,
              `code` int(11) DEFAULT NULL,
              `etime` int(11) DEFAULT NULL,
              `ctime` FLOAT DEFAULT NULL,
              `instant` int(11) DEFAULT '0',
              `data` TEXT DEFAULT NULL
            );
        },
        q{
            CREATE INDEX idx_gorgone_history_id ON gorgone_history (id);
        },
        q{
            CREATE INDEX idx_gorgone_history_token ON gorgone_history (token);
        },
        q{
            CREATE INDEX idx_gorgone_history_etime ON gorgone_history (etime);
        },
        q{
            CREATE INDEX idx_gorgone_history_code ON gorgone_history (code);
        },
        q{
            CREATE INDEX idx_gorgone_history_ctime ON gorgone_history (ctime);
        },
        q{
            CREATE INDEX idx_gorgone_history_instant ON gorgone_history (instant);
        },
        q{
            CREATE TABLE `gorgone_synchistory` (
              `id` int(11) NOT NULL,
              `ctime` FLOAT DEFAULT NULL,
              `last_id` int(11) DEFAULT NULL
            );
        },
        q{
            CREATE UNIQUE INDEX idx_gorgone_synchistory_id ON gorgone_synchistory (id);
        },
        q{
            CREATE TABLE `gorgone_target_fingerprint` (
              `id` INTEGER PRIMARY KEY,
              `target` varchar(2048) DEFAULT NULL,
              `fingerprint` varchar(4096) DEFAULT NULL
            );
        },
        q{
            CREATE INDEX idx_gorgone_target_fingerprint_target ON gorgone_target_fingerprint (target);
        },
        q{
            CREATE TABLE `gorgone_centreon_judge_spare` (
              `cluster_name` varchar(2048) NOT NULL,
              `status` int(11) NOT NULL,
              `data` TEXT DEFAULT NULL
            );
        },
        q{
            CREATE INDEX idx_gorgone_centreon_judge_spare_cluster_name ON gorgone_centreon_judge_spare (cluster_name);
        }
    ];
    foreach (@$schema) {
        my ($status, $sth) = $options{gorgone}->{db_gorgone}->query({ query => $_ });
        if ($status == -1) {
            $options{logger}->writeLogError("[core] create schema issue");
            exit(1);
        }
    }
}

sub init_database {
    my (%options) = @_;

    if ($options{type} =~ /sqlite/i && $options{db} =~ /dbname=(.*)/i) {
        my $sdb_path = File::Basename::dirname($1);
        File::Path::make_path($sdb_path);
    }
    $options{gorgone}->{db_gorgone} = gorgone::class::db->new(
        type => $options{type},
        db => $options{db},
        host => $options{host},
        port => $options{port},
        user => $options{user},
        password => $options{password},
        force => 2,
        logger => $options{logger}
    );
    $options{gorgone}->{db_gorgone}->set_inactive_destroy();
    if ($options{gorgone}->{db_gorgone}->connect() == -1) {
        $options{logger}->writeLogError("[core] Cannot connect. We quit!!");
        exit(1);
    }

    return if (!defined($options{autocreate_schema}) || $options{autocreate_schema} != 1);

    my $db_version = '1.0';
    my ($status, $sth) = $options{gorgone}->{db_gorgone}->query({ query => q{SELECT `value` FROM gorgone_information WHERE `key` = 'version'} });
    if ($status == -1) {
        ($status, $sth) = $options{gorgone}->{db_gorgone}->query({ query => q{SELECT 1 FROM gorgone_identity LIMIT 1} });
        if ($status == -1) {
            create_schema(gorgone => $options{gorgone}, logger => $options{logger}, version => $options{version});
            return ;
        }
    } else {
        my $row = $sth->fetchrow_arrayref();
        $db_version = $row->[0] if (defined($row));
    }

    $options{logger}->writeLogInfo("[core] update schema $db_version -> $options{version}");
    
    if ($db_version eq '1.0') {
        my $schema = [
            q{
                PRAGMA encoding = "UTF-8"
            },
            q{
                CREATE TABLE `gorgone_information` (
                  `key` varchar(1024) DEFAULT NULL,
                  `value` varchar(1024) DEFAULT NULL
                );
            },
            qq{
                INSERT INTO gorgone_information (`key`, `value`) VALUES ('version', '$options{version}');
            },
            q{
                ALTER TABLE `gorgone_identity` ADD COLUMN `mtime` int(11) DEFAULT NULL DEFAULT NULL;
            },
            q{
                ALTER TABLE `gorgone_identity` ADD COLUMN `oldkey` varchar(1024) DEFAULT NULL;
            },
            q{
                ALTER TABLE `gorgone_identity` ADD COLUMN `oldiv` varchar(1024) DEFAULT NULL;
            },
            q{
                ALTER TABLE `gorgone_identity` ADD COLUMN `iv` varchar(1024) DEFAULT NULL;
            }
        ];
        foreach (@$schema) {
            my ($status, $sth) = $options{gorgone}->{db_gorgone}->query({ query => $_ });
            if ($status == -1) {
                $options{logger}->writeLogError("[core] update schema issue");
                exit(1);
            }
        }
        $db_version = '22.04.0';
    }

    if ($db_version ne $options{version}) {
        $options{gorgone}->{db_gorgone}->query({ query => "UPDATE gorgone_information SET `value` = '$options{version}' WHERE `key` = 'version'" });
    }
}
        
1;
