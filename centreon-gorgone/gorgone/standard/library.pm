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
use ZMQ::LibZMQ4;
use ZMQ::Constants qw(:all);
use JSON::XS;
use File::Basename;
use Crypt::PK::RSA;
use Crypt::PRNG;
use Crypt::CBC;
use Data::Dumper;
use YAML 'LoadFile';
use File::Path;
use File::Basename;

my %zmq_type = ('ZMQ_ROUTER' => ZMQ_ROUTER, 'ZMQ_DEALER' => ZMQ_DEALER);

sub read_config {
    my (%options) = @_;
    
    my $config;
    eval {
        $config = LoadFile($options{config_file});
    };
    if ($@) {
        $options{logger}->writeLogError("Parsing config file error:");
        $options{logger}->writeLogError($@);
        exit(1);
    }
    
    return $config;
}

#######################
# Handshake functions
#######################

sub generate_keys {
    my (%options) = @_;

    my ($privkey, $pubkey);
    eval {
        my $pkrsa = Crypt::PK::RSA->new();
        $pkrsa->generate_key(256, 65537);
        $pubkey = $pkrsa->export_key_pem('public_x509');
        $privkey = $pkrsa->export_key_pem('private');
    };
    if ($@) {
        $options{logger}->writeLogError("Cannot generate server keys: $@");
        return 0;
    }

    return (1, $privkey, $pubkey);
}

sub loadpubkey {
    my (%options) = @_;
    my $quit = defined($options{noquit}) ? 0 : 1;
    my $string_key = '';

    if (defined($options{pubkey})) {
        if (!open FILE, "<" . $options{pubkey}) {
            $options{logger}->writeLogError("Cannot read file '$options{pubkey}': $!") if (defined($options{logger}));
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
    eval {
        $pubkey = Crypt::PK::RSA->new(\$string_key);
    };
    if ($@) {
        $options{logger}->writeLogError("Cannot load pubkey '$options{pubkey}': $@") if (defined($options{logger}));
        exit(1) if ($quit);
        return 0;
    }
    if ($pubkey->is_private()) {
        $options{logger}->writeLogError("'$options{pubkey}' is not a publickey") if (defined($options{logger}));
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
        $options{logger}->writeLogError("Cannot read file '$options{privkey}': $!");
        exit(1) if ($quit);
        return 0;
    }
    while (<FILE>) {
        $string_key .= $_;
    }
    close FILE;

    my $privkey;
    eval {
        $privkey = Crypt::PK::RSA->new(\$string_key);
    };
    if ($@) {
        $options{logger}->writeLogError("Cannot load privkey '$options{privkey}': $@");
        exit(1) if ($quit);
        return 0;
    }
    if (!$privkey->is_private()) {
        $options{logger}->writeLogError("'$options{privkey}' is not a privkey");
        exit(1) if ($quit);
        return 0;
    }

    return (1, $privkey);
}

sub zmq_core_pubkey_response {
    my (%options) = @_;
    
    if (defined($options{identity})) {
        zmq_sendmsg($options{socket}, pack('H*', $options{identity}), ZMQ_NOBLOCK | ZMQ_SNDMORE);
    }
    my $client_pubkey = $options{pubkey}->export_key_pem('public');
    my $msg = '[PUBKEY] [' . unpack('H*', $client_pubkey) . ']';

    zmq_sendmsg($options{socket}, $msg, ZMQ_NOBLOCK);
    return 0;
}

sub zmq_core_key_response {
    my (%options) = @_;
    
    if (defined($options{identity})) {
        zmq_sendmsg($options{socket}, pack('H*', $options{identity}), ZMQ_NOBLOCK | ZMQ_SNDMORE);
    }
    my $crypttext;
    eval {
        $crypttext = $options{client_pubkey}->encrypt("[KEY] [$options{hostname}] [" . $options{symkey} . "]", 'v1.5');
    };
    if ($@) {
        $options{logger}->writeLogError("Encoding issue: " .  $@);
        return -1;
    }

    
    zmq_sendmsg($options{socket}, unpack('H*', $crypttext), ZMQ_NOBLOCK);
    return 0;
}

sub zmq_core_response {
    my (%options) = @_;
    my $msg;
    my $response_type = defined($options{response_type}) ? $options{response_type} : 'ACK';
    
    if (defined($options{identity})) {
        zmq_sendmsg($options{socket}, pack('H*', $options{identity}), ZMQ_NOBLOCK | ZMQ_SNDMORE);
    }

    my $data = json_encode(data => { code => $options{code}, data => $options{data} });
    # We add 'target' for 'PONG', 'SYNCLOGS'. Like that 'gorgone-proxy can get it
    $msg = '[' . $response_type . '] [' . (defined($options{token}) ? $options{token} : '') . '] ' . ($response_type =~ /^PONG|SYNCLOGS$/ ? '[] ' : '') . $data;
    
    if (defined($options{cipher})) {
        my $cipher = Crypt::CBC->new(
            -key    => $options{symkey},
            -keysize => length($options{symkey}),
            -cipher => $options{cipher},
            -iv => $options{vector},
            -header => 'none',
            -literal_key => 1
        );
        $msg = $cipher->encrypt($msg);
    }
    zmq_sendmsg($options{socket}, $msg, ZMQ_NOBLOCK);
}

sub uncrypt_message {
    my (%options) = @_;
    my $plaintext;
    
    my $cipher = Crypt::CBC->new(
        -key    => $options{symkey},
        -keysize => length($options{symkey}),
        -cipher => $options{cipher},
        -iv => $options{vector},
        -header => 'none',
        -literal_key => 1
    );
    eval {
        $plaintext = $cipher->decrypt($options{message});
    };
    if ($@) {
        if (defined($options{logger})) {
            $options{logger}->writeLogError("Sym encrypt issue: " .  $@);
        }
        return (-1, $@);
    }
    return (0, $plaintext);
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

sub client_get_secret {
    my (%options) = @_;
    my $plaintext;

    eval {
        my $cryptedtext = pack('H*', $options{message});
        $plaintext = $options{privkey}->decrypt($cryptedtext, 'v1.5');
    };
    if ($@) {
        return (-1, "Decoding issue: $@");
    }

    $plaintext = unpack('H*', $plaintext);
    if ($plaintext !~ /^5b(.*?)5d(.*?)5b(.*?)5d(.*?)5b(.*)5d$/i) {
        return (-1, 'Wrong protocol');
    }

    my $hostname = pack('H*', $3);
    my $symkey = pack('H*', $5);
    return (0, 'ok', $symkey, $hostname);
}

sub client_helo_encrypt {
    my (%options) = @_;
    my $ciphertext;

    my $client_pubkey = $options{client_pubkey}->export_key_pem('public');
    $client_pubkey =~ s/\n/\\n/g;
    eval {
        $ciphertext = $options{server_pubkey}->encrypt('HELO', 'v1.5');
    };
    if ($@) {
        return (-1, "Encoding issue: $@");
    }

    return (0, '[' . $options{identity} . '] [' . $client_pubkey . '] [' . unpack('H*', $ciphertext) . ']');
}

sub is_client_can_connect {
    my (%options) = @_;
    my $plaintext;

    if ($options{message} !~ /\[(.+)\]\s+\[(.+)\]\s+\[(.+)\]$/ms) {
        $options{logger}->writeLogError("Decoding issue. Protocol not good");
        return -1;
    }

    my ($client, $client_pubkey_str, $cipher_text) = ($1, $2, $3);
    eval {
        $plaintext = $options{privkey}->decrypt(pack('H*', $cipher_text), 'v1.5');
    };
    if ($@) {
        $options{logger}->writeLogError("Decoding issue: " .  $@);
        return -1;
    }
    if ($plaintext ne 'HELO') {
        $options{logger}->writeLogError("Encrypted issue for HELO");
        return -1;
    }

    my ($client_pubkey);
    $client_pubkey_str =~ s/\\n/\n/g;
    eval {
        $client_pubkey = Crypt::PK::RSA->new(\$client_pubkey_str);
    };
    if ($@) {
        $options{logger}->writeLogError("Cannot load client pubkey '$client_pubkey': $@");
        return -1;
    }

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
        $options{logger}->writeLogError("client pubkey is not authorized. thumprint is '$thumbprint");
        return -1;
    }

    $options{logger}->writeLogInfo("Connection from $client");
    return (0, $client_pubkey);
}

sub is_handshake_done {
    my (%options) = @_;
    
    my ($status, $sth) = $options{dbh}->query("SELECT `key` FROM gorgone_identity WHERE identity = " . $options{dbh}->quote($options{identity}) . " ORDER BY id DESC LIMIT 1");
    return if ($status == -1);
    if (my $row = $sth->fetchrow_hashref()) {
        return 0 if (!defined($row->{key}) || $row->{key} eq '');
        return (1, pack('H*', $row->{key}));
    }
    return 0;
}

#######################
# internal functions
#######################

sub getthumbprint {
    my (%options) = @_;

    if ($options{gorgone}->{keys_loaded} == 0) {
        return (1, { action => 'getthumbprint', message => 'no public key loaded' }, 'GETTHUMBPRINT');
    }
    my $thumbprint = $options{gorgone}->{server_pubkey}->export_key_jwk_thumbprint('SHA256');
    return (0, { action => 'getthumbprint', message => 'ok', data => { thumbprint => $thumbprint } }, 'GETTHUMBPRINT');
}

sub information {
    my (%options) = @_;

    my $data = {
        counters => $options{gorgone}->{counters},
        modules => $options{gorgone}->{modules_id},
    };
    return (0, { action => 'information', message => 'ok', data => $data }, 'INFORMATION');
}

sub unloadmodule {
    my (%options) = @_;

    my $data;
    eval {
        $data = JSON::XS->new->utf8->decode($options{data});
    };
    if ($@) {
        return (1, { message => 'request not well formatted' });
    }

    if (defined($data->{content}->{package}) && defined($options{gorgone}->{modules_register}->{ $data->{content}->{package} })) {
        $options{gorgone}->{modules_register}->{ $data->{content}->{package} }->{gently}->(logger => $options{gorgone}->{logger});
        return (0, { action => 'unloadmodule', message => "module '$data->{content}->{package}' unload in progress" }, 'UNLOADMODULE');
    }
    if (defined($data->{content}->{name}) &&
        defined($options{gorgone}->{modules_id}->{$data->{content}->{name}}) && 
        defined($options{gorgone}->{modules_register}->{ $options{gorgone}->{modules_id}->{$data->{content}->{name}} })) {
        $options{gorgone}->{modules_register}->{ $options{gorgone}->{modules_id}->{$data->{content}->{name}} }->{gently}->(logger => $options{gorgone}->{logger});
        return (0, { action => 'unloadmodule', message => "module '$data->{content}->{name}' unload in progress" }, 'UNLOADMODULE');
    }

    return (1, { action => 'unloadmodule', message => 'cannot find unload module' }, 'UNLOADMODULE');
}

sub loadmodule {
    my (%options) = @_;

    my $data;
    eval {
        $data = JSON::XS->new->utf8->decode($options{data});
    };
    if ($@) {
        return (1, { message => 'request not well formatted' });
    }

    if ($options{gorgone}->load_module(config_module => $data->{content})) {
        $options{gorgone}->{modules_register}->{ $data->{content}->{package} }->{init}->(
            id => $options{gorgone}->{id},
            logger => $options{gorgone}->{logger},
            poll => $options{gorgone}->{poll},
            external_socket => $options{gorgone}->{external_socket},
            internal_socket => $options{gorgone}->{internal_socket},
            dbh => $options{gorgone}->{db_gorgone},
            modules_events => $options{gorgone}->{modules_events},
        );
        return (0, { action => 'loadmodule', message => "module '$data->{content}->{name}' is loaded" }, 'LOADMODULE');
    }

    return (1, { action => 'loadmodule', message => "cannot load module '$data->{content}->{name}'" }, 'LOADMODULE');
}

sub synclogs {
    my (%options) = @_;

    my $data;
    eval {
        $data = JSON::XS->new->utf8->decode($options{data});
    };
    if ($@) {
        return (1, { message => 'request not well formatted' });
    }

    if (!defined($data->{data}->{id})) {
        return (1, { action => 'synclog', message => 'please set id for synclog' });
    }

    if (defined($options{gorgone_config}->{gorgonecore}->{proxy_name}) && defined($options{gorgone}->{modules_id}->{$options{gorgone_config}->{gorgonecore}->{proxy_name}})) {
        my $name = $options{gorgone}->{modules_id}->{$options{gorgone_config}->{gorgonecore}->{proxy_name}};
        my $method;
        if (defined($name) && ($method = $name->can('synclog'))) {
            $method->(dbh => $options{gorgone}->{db_gorgone});
            return (0, { action => 'synclog', message => 'synclog launched' });
        }
    }

    return (1, { action => 'synclog', message => 'no proxy module' });
}

sub constatus {
    my (%options) = @_;

    if (defined($options{gorgone_config}->{gorgonecore}->{proxy_name}) && defined($options{gorgone}->{modules_id}->{$options{gorgone_config}->{gorgonecore}->{proxy_name}})) {
        my $name = $options{gorgone}->{modules_id}->{$options{gorgone_config}->{gorgonecore}->{proxy_name}};
        my $method;
        if (defined($name) && ($method = $name->can('get_constatus_result'))) {
            return (0, { action => 'constatus', message => 'ok', data => $method->() }, 'CONSTATUS');
        }
    }
    
    return (1, { action => 'constatus', message => 'cannot get value' }, 'CONSTATUS');
}

sub setcoreid {
    my (%options) = @_;

    my $data;
    eval {
        $data = JSON::XS->new->utf8->decode($options{data});
    };
    if ($@) {
        return (1, { message => 'request not well formatted' });
    }

    if (!defined($data->{id})) {
        return (1, { action => 'setcoreid', message => 'please set id for setcoreid' });
    }

    if (defined($options{gorgone_config}->{gorgonecore}->{proxy_name}) && defined($options{gorgone}->{modules_id}->{$options{gorgone_config}->{gorgonecore}->{proxy_name}})) {
        my $name = $options{gorgone}->{modules_id}->{$options{gorgone_config}->{gorgonecore}->{proxy_name}};
        my $method;
        if (defined($name) && ($method = $name->can('setcoreid'))) {
            $method->(dbh => $options{dbh}, core_id => $data->{id}, logger => $options{logger});
        }
    }

    $options{logger}->writeLogInfo('[core] setcoreid changed ' .  $data->{id});
    $options{gorgone}->{id} = $data->{id};
    return (0, { action => 'setcoreid', message => 'setcoreid changed' });
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

    return (0, { action => 'ping', message => 'ping ok', id => $options{id}, data => $constatus }, 'PONG');
}

sub putlog {
    my (%options) = @_;

    my $data;
    eval {
        $data = JSON::XS->new->utf8->decode($options{data});
    };
    if ($@) {
        return (1, { message => 'request not well formatted' });
    }
    
    my $status = add_history(
        dbh => $options{gorgone}->{db_gorgone}, 
        etime => $data->{etime},
        token => $data->{token},
        data => json_encode(data => $data->{data}, logger => $options{logger}),
        code => $data->{code}
    );
    if ($status == -1) {
        return (1, { message => 'database issue' });
    }
    return (0, { message => 'message inserted' });
}

sub getlog {
    my (%options) = @_;

    my $data;
    eval {
        $data = JSON::XS->new->utf8->decode($options{data});
    };
    if ($@) {
        return (1, { message => 'request not well formatted' });
    }
    
    my %filters = ();
    my ($filter, $filter_append) = ('', '');
    
    foreach ((['id', '>'], ['token', '='], ['ctime', '>='], ['etime', '>'], ['code', '='])) {
        if (defined($data->{${$_}[0]}) && $data->{${$_}[0]} ne '') {
            $filter .= $filter_append . ${$_}[0] . ' ' . ${$_}[1] . ' ' . $options{gorgone}->{db_gorgone}->quote($data->{${$_}[0]});
            $filter_append = ' AND ';
        }
    }
    
    if ($filter eq '') {
        return (1, { message => 'need at least one filter' });
    }

    my $query = "SELECT * FROM gorgone_history WHERE " . $filter;
    $query .= " ORDER BY id DESC LIMIT " . $data->{limit} if (defined($data->{limit}) && $data->{limit} ne '');
    
    my ($status, $sth) = $options{gorgone}->{db_gorgone}->query($query);
    if ($status == -1) {
        return (1, { message => 'database issue' });
    }

    my @result;
    my $results = $sth->fetchall_hashref('id');
    foreach (sort keys %{$results}) {
        push @result, $results->{$_};
    }
    
    return (0, { action => 'getlog', result => \@result, id => $options{gorgone}->{id} });
}

sub kill {
    my (%options) = @_;

}

#######################
# Database functions
#######################

sub update_identity {
    my (%options) = @_;

    my ($status, $sth) = $options{dbh}->query("UPDATE gorgone_identity SET `ctime` = " . $options{dbh}->quote(time()) . " WHERE `identity` = " . $options{dbh}->quote($options{identity}));
    return $status;
}

sub add_identity {
    my (%options) = @_;

    my ($status, $sth) = $options{dbh}->query("INSERT INTO gorgone_identity (`ctime`, `identity`, `key`) VALUES (" . 
                  $options{dbh}->quote(time()) . ", " . $options{dbh}->quote($options{identity}) . ", " . $options{dbh}->quote(unpack('H*', $options{symkey})) . ")");
    return $status;
}

sub add_history {
    my (%options) = @_;

    if (defined($options{data}) && defined($options{json_encode})) {
        return -1 if (!($options{data} = json_encode(data => $options{data}, logger => $options{logger})));
    }
    if (!defined($options{ctime})) {
        $options{ctime} = time();
    }
    if (!defined($options{etime})) {
        $options{etime} = time();
    }
    
    my @names = ();
    my @values = ();
    foreach (('data', 'token', 'ctime', 'etime', 'code')) {
        if (defined($options{$_})) {
            push @names, $_;
            push @values, $options{dbh}->quote($options{$_});
        }
    }
    my ($status, $sth) = $options{dbh}->query("INSERT INTO gorgone_history (" . join(',', @names) . ") VALUES (" . 
                 join(',', @values) . ")");
    return $status;
}

#######################
# Misc functions
#######################

sub json_encode {
    my (%options) = @_;
    
    my $data;
    eval {
        $data = JSON::XS->new->utf8->encode($options{data});
    };
    if ($@) {
        if (defined($options{logger})) {
            $options{logger}->writeLogError("Cannot encode json data: $@");
        }
        return undef;
    }

    return $data;
}

#######################
# Global ZMQ functions
#######################

sub connect_com {
    my (%options) = @_;
    
    my $context = zmq_init();
    my $socket = zmq_socket($context, $zmq_type{$options{zmq_type}});
    if (!defined($socket)) {
        $options{logger}->writeLogError("Can't setup server: $!");
        exit(1);
    }

    zmq_setsockopt($socket, ZMQ_IDENTITY, $options{name});
    zmq_setsockopt($socket, ZMQ_LINGER, defined($options{linger}) ? $options{linger} : 0); # 0 we discard
    zmq_setsockopt($socket, ZMQ_SNDHWM, defined($options{sndhwm}) ? $options{sndhwm} : 0);
    zmq_setsockopt($socket, ZMQ_RCVHWM, defined($options{rcvhwm}) ? $options{sndhwm} : 0);
    #zmq_setsockopt($socket, ZMQ_CONNECT_TIMEOUT, 60000); # for tcp: 60 seconds
    zmq_setsockopt($socket, ZMQ_RECONNECT_IVL, 1000);
    zmq_connect($socket, $options{type} . '://' . $options{path});
    return $socket;
}

sub create_com {
    my (%options) = @_;
    
    my $context = zmq_init();
    my $socket = zmq_socket($context, $zmq_type{$options{zmq_type}});
    if (!defined($socket)) {
        $options{logger}->writeLogError("Can't setup server: $!");
        exit(1);
    }

    zmq_setsockopt($socket, ZMQ_IDENTITY, $options{name});
    zmq_setsockopt($socket, ZMQ_LINGER, 0); # we discard    
    if ($options{type} eq 'tcp') {
        zmq_bind($socket, 'tcp://' . $options{path});
    } elsif ($options{type} eq 'ipc') {
        if (zmq_bind($socket, 'ipc://' . $options{path}) == -1) {
            $options{logger}->writeLogError("Cannot bind ipc '$options{path}': $!");
            # try create dir
            $options{logger}->writeLogError("Maybe directory not exist. We try to create it!!!");
            if (!mkdir(dirname($options{path}))) {
                zmq_close($socket);
                exit(1);
            }
            if (zmq_bind($socket, 'ipc://' . $options{path}) == -1) {
                $options{logger}->writeLogError("Cannot bind ipc '$options{path}': $!");
                zmq_close($socket);
                exit(1);
            }
        }
    } else {
        $options{logger}->writeLogError("zmq type '$options{type}' not managed");
        zmq_close($socket);
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

    if (defined($data)) {
        if (defined($options{json_encode})) {
            $data = json_encode(data => $data, logger => $options{logger});
        }
    } else {
        $data = json_encode(data => {}, logger => $options{logger});
    }
    
    return '[' . $action . '] [' . $token . '] [' . $target . '] ' . $data;
}

sub zmq_send_message {
    my (%options) = @_;
    my $message = $options{message};
    
    if (!defined($message)) {
        $message = build_protocol(%options);
    }
    if (defined($options{identity})) {
        zmq_sendmsg($options{socket}, $options{identity}, ZMQ_NOBLOCK | ZMQ_SNDMORE);
    }    
    if (defined($options{cipher})) {
        my $cipher = Crypt::CBC->new(
            -key    => $options{symkey},
            -keysize => length($options{symkey}),
            -cipher => $options{cipher},
            -iv => $options{vector},
            -header => 'none',
            -literal_key => 1
        );
        $message = $cipher->encrypt($message);
    }
    zmq_sendmsg($options{socket}, $message, ZMQ_NOBLOCK);
}

sub zmq_dealer_read_message {
    my (%options) = @_;
    
    # Process all parts of the message
    my $message = zmq_recvmsg($options{socket});
    my $data = zmq_msg_data($message);
 
    return $data;
}

sub zmq_read_message {
    my (%options) = @_;
    
    # Process all parts of the message
    my $message = zmq_recvmsg($options{socket});
    my $identity = zmq_msg_data($message);
    $message = zmq_recvmsg($options{socket});
    my $data = zmq_msg_data($message);
 
    return (unpack('H*', $identity), $data);
}

sub zmq_still_read {
    my (%options) = @_;
    
    return zmq_getsockopt($options{socket}, ZMQ_RCVMORE);        
}

sub add_zmq_pollin {
    my (%options) = @_;

    push @{$options{poll}}, {
        socket  => $options{socket},
        events  => ZMQ_POLLIN,
        callback => $options{callback},
    };
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

    if (defined($options{autocreate_schema}) && $options{autocreate_schema} == 1) {
        my $requests = [
            q{
                CREATE TABLE IF NOT EXISTS `gorgone_identity` (
                  `id` INTEGER PRIMARY KEY,
                  `ctime` int(11) DEFAULT NULL,
                  `identity` varchar(2048) DEFAULT NULL,
                  `key` varchar(4096) DEFAULT NULL,
                  `parent` int(11) DEFAULT '0'
                );
            },
            q{
                CREATE INDEX IF NOT EXISTS idx_gorgone_identity ON gorgone_identity (identity);
            },
            q{
                CREATE INDEX IF NOT EXISTS idx_gorgone_parent ON gorgone_identity (parent);
            },
            q{
                CREATE TABLE IF NOT EXISTS `gorgone_history` (
                  `id` INTEGER PRIMARY KEY,
                  `token` varchar(2048) DEFAULT NULL,
                  `code` int(11) DEFAULT NULL,
                  `etime` int(11) DEFAULT NULL,
                  `ctime` int(11) DEFAULT NULL,
                  `instant` int(11) DEFAULT '0',
                  `data` TEXT DEFAULT NULL
                );
            },
            q{
                CREATE INDEX IF NOT EXISTS idx_gorgone_history_id ON gorgone_history (id);
            },
            q{
                CREATE INDEX IF NOT EXISTS idx_gorgone_history_token ON gorgone_history (token);
            },
            q{
                CREATE INDEX IF NOT EXISTS idx_gorgone_history_etime ON gorgone_history (etime);
            },
            q{
                CREATE INDEX IF NOT EXISTS idx_gorgone_history_code ON gorgone_history (code);
            },
            q{
                CREATE INDEX IF NOT EXISTS idx_gorgone_history_ctime ON gorgone_history (ctime);
            },
            q{
                CREATE INDEX IF NOT EXISTS idx_gorgone_history_instant ON gorgone_history (instant);
            },
            q{
                CREATE TABLE IF NOT EXISTS `gorgone_synchistory` (
                  `id` int(11) DEFAULT NULL,
                  `ctime` int(11) DEFAULT NULL,
                  `last_id` int(11) DEFAULT NULL
                );
            },
            q{
                CREATE INDEX IF NOT EXISTS idx_gorgone_synchistory_id ON gorgone_synchistory (id);
            },
            q{
                CREATE TABLE IF NOT EXISTS `gorgone_target_fingerprint` (
                  `id` INTEGER PRIMARY KEY,
                  `target` varchar(2048) DEFAULT NULL,
                  `fingerprint` varchar(4096) DEFAULT NULL
                );
            },
            q{
                CREATE INDEX IF NOT EXISTS idx_gorgone_target_fingerprint_target ON gorgone_target_fingerprint (target);
            },
        ];
        foreach (@$requests) {
            $options{gorgone}->{db_gorgone}->query($_);
        }
    }
}
        
1;
