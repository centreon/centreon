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

package centreon::gorgone::common;

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

my %zmq_type = ('ZMQ_ROUTER' => ZMQ_ROUTER, 'ZMQ_DEALER' => ZMQ_DEALER);

sub read_config {
    my (%options) = @_;
    
    my $config;
    eval {
        $config = LoadFile($options{config_file});
    };
    if ($@) {
        $options{logger}->writeLogError("Parsinig extra config file error:");
        $options{logger}->writeLogError($@);
        exit(1);
    }
    
    return $config;
}

#######################
# Handshake functions
#######################

sub loadpubkey {
    my (%options) = @_;
    my $string_key = '';

    if (!open FILE, "<" . $options{pubkey}) {
        $options{logger}->writeLogError("Cannot read file '$options{pubkey}': $!");
        exit(1);
    }
    while (<FILE>) {
        $string_key .= $_;
    }
    close FILE;
    
    my $pubkey;
    eval {
        $pubkey = Crypt::PK::RSA->new(\$string_key);
    };
    if ($@) {
        $options{logger}->writeLogError("Cannot load pubkey '$options{pubkey}': $@");
        exit(1);
    }
    if ($pubkey->is_private()) {
        $options{logger}->writeLogError("'$options{pubkey}' is not a publickey");
        exit(1);
    }
    
    return $pubkey;
}

sub loadprivkey {
    my (%options) = @_;
    my $string_key = '';
    
    if (!open FILE, "<" . $options{privkey}) {
        $options{logger}->writeLogError("Cannot read file '$options{privkey}': $!");
        exit(1);
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
        exit(1);
    }
    if (!$privkey->is_private()) {
        $options{logger}->writeLogError("'$options{privkey}' is not a privkey");
        exit(1);
    }

    return $privkey;
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
    zmq_sendmsg($options{socket}, $crypttext, ZMQ_NOBLOCK);
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
    # We add 'target' for 'PONG', 'CONSTATUS'. Like that 'gorgone-proxy can get it
    $msg = '[' . $response_type . '] [' . (defined($options{token}) ? $options{token} : '') . '] ' . ($response_type eq 'PONG' ? '[] ' : '') . $data;
    
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
    
    my $token = Crypt::PRNG::random_bytes_hex(64);
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
        $plaintext = $options{privkey}->decrypt($options{message}, 'v1.5');
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
    return (0, $symkey, $hostname);
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

    return (0, '[' . $options{identity} . '] [' . $client_pubkey . '] [' . $ciphertext . ']');
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
        $plaintext = $options{privkey}->decrypt($cipher_text, 'v1.5');
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
    
    my ($status, $sth) = $options{dbh}->query("SELECT `key` FROM gorgone_identity WHERE identity = " . $options{dbh}->quote($options{identity}) . " ORDER BY id DESC");
    return if ($status == -1);
    if (my $row = $sth->fetchrow_hashref()) {
        return (1, pack('H*', $row->{key}));
    }
    return 0;
}

#######################
# internal functions
#######################

sub constatus {
    my (%options) = @_;
    
    if (defined($options{gorgone}->{modules_register}->{ $options{gorgone}->{modules_id}->{$options{gorgone_config}->{gorgonecore}->{proxy_name}} })) {
        my $name = $options{gorgone_config}->{modules}->{$options{gorgone_config}->{gorgonecore}->{proxy_name}}->{module};
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

    $options{logger}->writeLogInfo('[core] setcoreid changed ' .  $data->{id});
    $options{gorgone}->{id} = $data->{id};
    return (0, { action => 'setcoreid', message => 'setcoreid changed' });
}

sub ping {
    my (%options) = @_;

    #my $status = add_history(dbh => $options{gorgone}->{db_gorgone}, 
    #                         token => $options{token}, logger => $options{logger}, code => 0);
    return (0, { action => 'ping', message => 'ping ok', id => $options{id} }, 'PONG');
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
    
    my ($status, $sth) = $options{gorgone}->{db_gorgone}->query("SELECT * FROM gorgone_history WHERE " . $filter);
    if ($status == -1) {
        return (1, { message => 'database issue' });
    }
    
    return (0, { action => 'getlog', result => $sth->fetchall_hashref('id'), id => $options{gorgone}->{id} });
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
        
1;
