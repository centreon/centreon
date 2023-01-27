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

use strict;
use warnings;

use ZMQ::LibZMQ4;
use ZMQ::Constants qw(:all);
use JSON::XS;
use UUID;
use Data::Dumper;
use Sys::Hostname;
use gorgone::class::clientzmq;
use gorgone::standard::library;

my ($client, $client2);
my $identities_token = {};
my $stopped = {};
my $results = {};

sub get_command_result {
    my ($current_retries, $retries) = (0, 4);
    $stopped->{$client2->{identity}} = '^(1|2)$'; 
    $client2->send_message(
        action => 'COMMAND', data => { content => { command => 'ls /' } }, target => 100, 
        json_encode => 1
    );
    while (1) {
        my $poll = [];
     
        $client2->ping(poll => $poll);
        my $rev = zmq_poll($poll, 15000);
        
        if (defined($results->{$client2->{identity}})) {
            print "The result: " . Data::Dumper::Dumper($results->{$client2->{identity}});
            last;
        }
        
        if (!defined($rev) || $rev == 0) {
            $current_retries++;
            last if ($current_retries >= $retries);
            
            if (defined($identities_token->{$client2->{identity}})) {
                # We ask a sync
                print "==== send logs ===\n";
                $client2->send_message(action => 'GETLOG', target => 150, json_encode => 1);
                $client2->send_message(action => 'GETLOG', token => $identities_token->{$client2->{identity}}, data => { token => $identities_token->{$client2->{identity}} }, 
                                       json_encode => 1);
            }
        }
        
    }
}

sub read_response_result {
    my (%options) = @_;
    
    $options{data} =~ /^\[ACK\]\s+\[(.*?)\]\s+(.*)$/m;
    $identities_token->{$options{identity}} = $1;
        
    my $data;
    eval {
        $data = JSON::XS->new->utf8->decode($2);
    };
    if ($@) {
        return undef;
    }
        
    if (defined($data->{data}->{action}) && $data->{data}->{action} eq 'getlog') {
        if (defined($data->{data}->{result})) {
            foreach my $key (keys %{$data->{data}->{result}}) {
                if ($data->{data}->{result}->{$key}->{code} =~ /$stopped->{$options{identity}}/) {
                    $results->{$options{identity}} = $data->{data}->{result};
                    last;
                }
            }
        }
    }
}

sub read_response {
    my (%options) = @_;
    
    print "==== PLOP = " . $options{data} . "===\n";
}

my ($symkey, $status, $hostname, $ciphertext);

my $uuid;
#$uuid = 'toto';
UUID::generate($uuid);

#$client = gorgone::class::clientzmq->new(
#    identity => 'toto', 
#    cipher => 'Cipher::AES', 
#    vector => '0123456789012345',
#    server_pubkey => 'keys/central/pubkey.crt',
#    client_pubkey => 'keys/poller/pubkey.crt',
#    client_privkey => 'keys/poller/privkey.pem',
#    target_type => 'tcp',
#    target_path => '127.0.0.1:5555',
#    ping => 60,
#);
#$client->init(callback => \&read_response);
$client2 = gorgone::class::clientzmq->new(
    identity => 'tata', 
    cipher => 'Cipher::AES',
    vector => '0123456789012345',
    server_pubkey => 'keys/central/pubkey.crt',
    client_pubkey => 'keys/poller/pubkey.crt',
    client_privkey => 'keys/poller/privkey.pem',
    target_type => 'tcp',
    target_path => '127.0.0.1:5555'
);
$client2->init(callback => \&read_response_result);

#$client->send_message(
#    action => 'SCOMRESYNC',
#    data => { container_id => 'toto' }, 
#    json_encode => 1
#);
#$client->send_message(action => 'PUTLOG', data => { code => 120, etime => time(), token => 'plopplop', data => { 'nawak' => 'nawak2' } },
#                      json_encode => 1);
#$client2->send_message(action => 'RELOADCRON', data => { }, 
#                       json_encode => 1);

# We send a request to a poller
#$client2->send_message(action => 'ENGINECOMMAND', data => { command => '[1417705150] ENABLE_HOST_CHECK;host1', engine_pipe => '/var/lib/centreon-engine/rw/centengine.cmd' }, target => 120, 
#                       json_encode => 1);

#$client2->send_message(action => 'COMMAND', data => { content => { command => 'ls' } }, target => 150, 
#                       json_encode => 1);
#$client2->send_message(action => 'CONSTATUS');
$client2->send_message(
    action => 'LOADMODULE',
    data => { content => { name => 'engine', package => 'gorgone::modules::centreon::engine::hooks', enable => 'true', command_file => 'plop' } },
    json_encode => 1
);

# It will transform
#$client2->send_message(action => 'GETLOG', data => { cmd => 'ls' }, target => 120, 
#                       json_encode => 1);
#$client2->send_message(action => 'GETLOG', data => {}, target => 140, 
#                       json_encode => 1);

get_command_result();

#while (1) {
#    my $poll = [];

#    $client->ping(poll => $poll);
#    $client2->ping(poll => $poll);
#    zmq_poll($poll, 5000);
#}

while (1) {
    #my $poll = [$client->get_poll(), $client2->get_poll()];
    my $poll = [$client2->get_poll()];

#    $client->ping(poll => $poll);
#    $client2->ping(poll => $poll);
    zmq_poll($poll, 5000);
}

$client->close();
$client2->close();  
exit(0);

#zmq_close($requester);

