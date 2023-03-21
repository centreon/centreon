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

package gorgone::modules::core::proxy::hooks;

use warnings;
use strict;
use JSON::XS;
use gorgone::class::frame;
use gorgone::standard::misc;
use gorgone::class::core;
use gorgone::standard::library;
use gorgone::standard::constants qw(:all);
use gorgone::modules::core::proxy::class;
use File::Basename;
use MIME::Base64;
use Digest::MD5::File qw(file_md5_hex);
use Fcntl;
use Time::HiRes;
use Try::Tiny;
use Archive::Tar;
use File::Find;

$Archive::Tar::SAME_PERMISSIONS = 1;
$Archive::Tar::WARN = 0;

=begin comment
for each proxy processus, we have: 
    one control channel (DEALER identity: gorgone-proxy-$poolid)
    one channel by client (DEALER identity: gorgone-proxy-channel-$nodeid)
=cut

use constant NAMESPACE => 'core';
use constant NAME => 'proxy';
use constant EVENTS => [
    { event => 'PROXYREADY' },
    { event => 'REMOTECOPY', uri => '/remotecopy', method => 'POST' },
    { event => 'SETLOGS' }, # internal. Shouldn't be used by third party clients
    { event => 'PONG' }, # internal. Shouldn't be used by third party clients
    { event => 'REGISTERNODES' }, # internal. Shouldn't be used by third party clients
    { event => 'UNREGISTERNODES' }, # internal. Shouldn't be used by third party clients
    { event => 'PROXYADDNODE' }, # internal. Shouldn't be used by third party clients
    { event => 'PROXYDELNODE' }, # internal. Shouldn't be used by third party clients
    { event => 'PROXYADDSUBNODE' }, # internal. Shouldn't be used by third party clients
    { event => 'PONGRESET' }, # internal. Shouldn't be used by third party clients
    { event => 'PROXYCLOSECONNECTION' },
    { event => 'PROXYSTOPREADCHANNEL' }
];

my $config_core;
my $config;

my $synctime_error = 0;
my $synctime_nodes = {}; # get last time retrieved
my $synctime_lasttime;
my $synctime_option;
my $synctimeout_option;
my $ping_interval;

my $last_pong = {}; 
my $register_nodes = {};
# With static routes we have a pathscore. Dynamic no pathscore.
# Dynamic comes from PONG result
# algo is: we use static routes first. after we use dynamic routes
#  {
#     subnode_id => {
#         static => {
#              parent_id1 => 1,
#              parent_id2 => 2,
#         },
#         dynamic => {
#              parent_id3 => 1,
#              parent_id5 => 1,
#         }
#     }
#  }
#
my $register_subnodes = {};
my $constatus_ping = {};
my $parent_ping = {};
my $pools = {};
my $pools_pid = {};
my $nodes_pool = {};
my $prevails = {};
my $prevails_subnodes = {};
my $rr_current = 0;
my $stop = 0;

# httpserver is only for pull wss client
my $httpserver = {};

my ($external_socket, $core_id);

sub register {
    my (%options) = @_;

    $config = $options{config};
    $config_core = $options{config_core};

    $synctime_option = defined($config->{synchistory_time}) ? $config->{synchistory_time} : 60;
    $synctimeout_option = defined($config->{synchistory_timeout}) ? $config->{synchistory_timeout} : 30;
    $ping_interval = defined($config->{ping}) ? $config->{ping} : 60;
    $config->{pong_discard_timeout} = defined($config->{pong_discard_timeout}) ? $config->{pong_discard_timeout} : 300;
    $config->{pong_max_timeout} = defined($config->{pong_max_timeout}) ? $config->{pong_max_timeout} : 3;
    $config->{pool} = defined($config->{pool}) && $config->{pool} =~ /(\d+)/ ? $1 : 5;
    return (1, NAMESPACE, NAME, EVENTS);
}

sub init {
    my (%options) = @_;

    $synctime_lasttime = Time::HiRes::time();
    $core_id = $options{id};
    $external_socket = $options{external_socket};
    for my $pool_id (1..$config->{pool}) {
        create_child(dbh => $options{dbh}, pool_id => $pool_id, logger => $options{logger});
    }
    if (defined($config->{httpserver}->{enable}) && $config->{httpserver}->{enable} eq 'true') {
        create_httpserver_child(dbh => $options{dbh}, logger => $options{logger});
    }
}

sub routing {
    my (%options) = @_;

    my $data = $options{frame}->decodeData();
    if (!defined($data)) {
        $options{logger}->writeLogError("[proxy] Cannot decode json data: " . $options{frame}->getLastError());
        gorgone::standard::library::add_history({
            dbh => $options{dbh},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => 'proxy - cannot decode json' },
            json_encode => 1
        });
        return undef;
    }

    if ($options{action} eq 'PONG') {
        return undef if (!defined($data->{data}->{id}) || $data->{data}->{id} eq '');
        $constatus_ping->{ $data->{data}->{id} }->{in_progress_ping} = 0;
        $constatus_ping->{ $data->{data}->{id} }->{ping_timeout} = 0;
        $last_pong->{ $data->{data}->{id} } = time();
        $constatus_ping->{ $data->{data}->{id} }->{last_ping_recv} = time();
        $constatus_ping->{ $data->{data}->{id} }->{nodes} = $data->{data}->{data};
        $constatus_ping->{ $data->{data}->{id} }->{ping_ok}++;
        register_subnodes(%options, id => $data->{data}->{id}, subnodes => $data->{data}->{data});
        $options{logger}->writeLogInfo("[proxy] Pong received from '" . $data->{data}->{id} . "'");
        return undef;
    }

    if ($options{action} eq 'PONGRESET') {
        return undef if (!defined($data->{data}->{id}) || $data->{data}->{id} eq '');
        if (defined($constatus_ping->{ $data->{data}->{id} })) {
            $constatus_ping->{ $data->{data}->{id} }->{in_progress_ping} = 0;
            $constatus_ping->{ $data->{data}->{id} }->{ping_timeout} = 0;
            $constatus_ping->{ $data->{data}->{id} }->{ping_failed}++;
        }
        $options{logger}->writeLogInfo("[proxy] PongReset received from '" . $data->{data}->{id} . "'");
        return undef;
    }

    if ($options{action} eq 'UNREGISTERNODES') {
        unregister_nodes(%options, data => $data);
        return undef;
    }

    if ($options{action} eq 'REGISTERNODES') {
        register_nodes(%options, data => $data);
        return undef;
    }

    if ($options{action} eq 'PROXYREADY') {
        if (defined($data->{pool_id})) {
            $pools->{ $data->{pool_id} }->{ready} = 1;
            # we sent proxyaddnode to sync
            foreach my $node_id (keys %$nodes_pool) {
                next if ($nodes_pool->{$node_id} != $data->{pool_id});
                routing(
                    action => 'PROXYADDNODE',
                    target => $node_id,
                    frame => gorgone::class::frame->new(data => $register_nodes->{$node_id}),
                    gorgone => $options{gorgone},
                    dbh => $options{dbh},
                    logger => $options{logger}
                );
            }
        } elsif (defined($data->{httpserver})) {
            $httpserver->{ready} = 1;
        } elsif (defined($data->{node_id}) && defined($synctime_nodes->{ $data->{node_id} })) {
            $synctime_nodes->{ $data->{node_id} }->{channel_ready} = 1;
        }
        return undef;
    }

    if ($options{action} eq 'SETLOGS') {
        setlogs(dbh => $options{dbh}, data => $data, token => $options{token}, logger => $options{logger});
        return undef;
    }

    my ($code, $is_ctrl_channel, $target_complete, $target_parent, $target) = pathway(
        action => $options{action},
        target => $options{target},
        dbh => $options{dbh},
        token => $options{token},
        gorgone => $options{gorgone},
        logger => $options{logger}
    );
    return if ($code == -1);

    # we check if we have all proxy connected
    if (gorgone::class::core::waiting_ready_pool() == 0) {
        gorgone::standard::library::add_history({
            dbh => $options{dbh},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => 'proxy - still not ready' },
            json_encode => 1
        });
        return ;
    }
    
    if ($options{action} eq 'GETLOG') {
        if (defined($register_nodes->{$target_parent}) && $register_nodes->{$target_parent}->{type} eq 'push_ssh') {
            gorgone::standard::library::add_history({
                dbh => $options{dbh},
                code => GORGONE_ACTION_FINISH_KO, token => $options{token},
                data => { message => "proxy - can't get log a ssh target or through a ssh node" },
                json_encode => 1
            });
            return undef;
        }

        if (defined($register_nodes->{$target})) {
            if ($synctime_nodes->{$target}->{synctime_error} == -1 && get_sync_time(dbh => $options{dbh}, node_id => $target) == -1) {
                gorgone::standard::library::add_history({
                    dbh => $options{dbh},
                    code => GORGONE_ACTION_FINISH_KO, token => $options{token},
                    data => { message => 'proxy - problem to getlog' },
                    json_encode => 1
                });
                return undef;
            }

            if ($synctime_nodes->{$target}->{in_progress} == 1) {
                gorgone::standard::library::add_history({
                    dbh => $options{dbh},
                    code => GORGONE_ACTION_FINISH_KO, token => $options{token},
                    data => { message => 'proxy - getlog already in progress' },
                    json_encode => 1
                });
                return undef;
            }

            # We put the good time to get        
            my $ctime = $synctime_nodes->{$target}->{ctime};
            $options{frame}->setData({ ctime => $ctime });
            $synctime_nodes->{$target}->{in_progress} = 1;
            $synctime_nodes->{$target}->{in_progress_time} = time();
        }
    }

    my $action = $options{action};
    my $bulk_actions;
    push @{$bulk_actions}, $options{frame}->getRawData();

    if ($options{action} eq 'REMOTECOPY' && defined($register_nodes->{$target_parent}) &&
        $register_nodes->{$target_parent}->{type} ne 'push_ssh') {
        $action = 'PROCESSCOPY';
        ($code, $bulk_actions) = prepare_remote_copy(
            dbh => $options{dbh},
            data => $data,
            target => $target_parent,
            token => $options{token},
            logger => $options{logger}
        );
        return if ($code == -1);
    }

    my $pool_id;
    if (defined($nodes_pool->{$target_parent})) {
        $pool_id = $nodes_pool->{$target_parent};
    } else {
        $pool_id = rr_pool();
        $nodes_pool->{$target_parent} = $pool_id;
    }

    my $identity = 'gorgone-proxy-' . $pool_id;
    if ($is_ctrl_channel == 0 && $synctime_nodes->{$target_parent}->{channel_ready} == 1) {
        $identity = 'gorgone-proxy-channel-' . $target_parent;
    }
    if ($register_nodes->{$target_parent}->{type} eq 'wss' || $register_nodes->{$target_parent}->{type} eq 'pullwss') {
        $identity = 'gorgone-proxy-httpserver';
    }

    foreach my $raw_data_ref (@{$bulk_actions}) {
        # Mode zmq pull
        if ($register_nodes->{$target_parent}->{type} eq 'pull') {
            pull_request(
                gorgone => $options{gorgone},
                dbh => $options{dbh},
                action => $action,
                raw_data_ref => $raw_data_ref,
                token => $options{token},
                target_parent => $target_parent,
                target => $target,
                logger => $options{logger}
            );
            next;
        }

        $options{gorgone}->send_internal_message(
            identity => $identity,
            action => $action,
            raw_data_ref => $raw_data_ref,
            token => $options{token},
            target => $target_complete,
            nosync => 1
        );
    }

    $options{gorgone}->router_internal_event();
}

sub gently {
    my (%options) = @_;

    $stop = 1;
    foreach my $pool_id (keys %$pools) {
        if (defined($pools->{$pool_id}->{running}) && $pools->{$pool_id}->{running} == 1) {
            $options{logger}->writeLogDebug("[proxy] Send TERM signal for pool '" . $pool_id . "'");
            CORE::kill('TERM', $pools->{$pool_id}->{pid});
        }
    }

    if (defined($httpserver->{running}) && $httpserver->{running} == 1) {
        $options{logger}->writeLogDebug("[action] Send TERM signal for httpserver");
        CORE::kill('TERM', $httpserver->{pid});
    }
}

sub kill {
    my (%options) = @_;

    foreach (keys %{$pools}) {
        if ($pools->{$_}->{running} == 1) {
            $options{logger}->writeLogDebug("[proxy] Send KILL signal for pool '" . $_ . "'");
            CORE::kill('KILL', $pools->{$_}->{pid});
        }
    }

    if (defined($httpserver->{running}) && $httpserver->{running} == 1) {
        $options{logger}->writeLogDebug("[action] Send KILL signal for httpserver");
        CORE::kill('KILL', $httpserver->{pid});
    }
}

sub kill_internal {
    my (%options) = @_;

}

sub check_create_child {
    my (%options) = @_;

    return if ($stop == 1);

    # Check if we need to create a child
    for my $pool_id (1..$config->{pool}) {
        if (!defined($pools->{$pool_id})) {
            create_child(dbh => $options{dbh}, pool_id => $pool_id, logger => $options{logger});
        }
    }
}

sub check {
    my (%options) = @_;

    my $count = 0;
    foreach my $pid (keys %{$options{dead_childs}}) {
        if (defined($httpserver->{pid}) && $httpserver->{pid} == $pid) {
            $httpserver = {};
            delete $options{dead_childs}->{$pid};
            if ($stop == 0) {
                create_httpserver_child(logger => $options{logger});
            }
            next;
        }

        # Not me
        next if (!defined($pools_pid->{$pid}));
        
        # If someone dead, we recreate
        my $pool_id = $pools_pid->{$pid};
        delete $pools->{$pools_pid->{$pid}};
        delete $pools_pid->{$pid};
        delete $options{dead_childs}->{$pid};
        if ($stop == 0) {
            create_child(dbh => $options{dbh}, pool_id => $pool_id, logger => $options{logger});
        }
    }

    check_create_child(dbh => $options{dbh}, logger => $options{logger});

    $count++  if (defined($httpserver->{running}) && $httpserver->{running} == 1);
    foreach (keys %$pools) {
        $count++  if ($pools->{$_}->{running} == 1);
    }

    # We check synclog/ping/ping request timeout 
    foreach (keys %$synctime_nodes) {
        if ($register_nodes->{$_}->{type} =~ /^(?:pull|wss|pullwss)$/ && $constatus_ping->{$_}->{in_progress_ping} == 1) {
            my $ping_timeout = defined($register_nodes->{$_}->{ping_timeout}) ? $register_nodes->{$_}->{ping_timeout} : 30;
            if ((time() - $constatus_ping->{$_}->{in_progress_ping_pull}) > $ping_timeout) {
                $constatus_ping->{$_}->{in_progress_ping} = 0;
                $options{logger}->writeLogInfo("[proxy] Ping timeout from '" . $_ . "'");
            }
        }
        if ($register_nodes->{$_}->{type} !~ /^(?:pull|wss|pullwss)$/ && $constatus_ping->{$_}->{in_progress_ping} == 1) {
            if (time() - $constatus_ping->{ $_ }->{last_ping_sent} > $config->{pong_discard_timeout}) {
                $options{logger}->writeLogInfo("[proxy] Ping timeout from '" . $_ . "'");
                $constatus_ping->{$_}->{in_progress_ping} = 0;
                $constatus_ping->{$_}->{ping_timeout}++;
                $constatus_ping->{$_}->{ping_failed}++;
                if (($constatus_ping->{$_}->{ping_timeout} % $config->{pong_max_timeout}) == 0) {
                    $options{logger}->writeLogInfo("[proxy] Ping max timeout reached from '" . $_ . "'");
                    routing(
                        target => $_,
                        action => 'PROXYCLOSECONNECTION',
                        frame => gorgone::class::frame->new(data => { id => $_ }),
                        gorgone => $options{gorgone},
                        dbh => $options{dbh},
                        logger => $options{logger}
                    );
                }
            }
        }

        if ($synctime_nodes->{$_}->{in_progress} == 1 && 
            time() - $synctime_nodes->{$_}->{in_progress_time} > $synctimeout_option) {
            gorgone::standard::library::add_history({
                dbh => $options{dbh},
                code => GORGONE_ACTION_FINISH_KO,
                data => { message => "proxy - getlog in timeout for '$_'" },
                json_encode => 1
            });
            $synctime_nodes->{$_}->{in_progress} = 0;
        }
    }

    # We check if we need synclogs
    if ($stop == 0 &&
        time() - $synctime_lasttime > $synctime_option) {
        $synctime_lasttime = time();
        full_sync_history(gorgone => $options{gorgone}, dbh => $options{dbh}, logger => $options{logger});
    }
    
    if ($stop == 0) {
        ping_send(gorgone => $options{gorgone}, dbh => $options{dbh}, logger => $options{logger});
    }

    # We clean all parents
    foreach (keys %$parent_ping) {
        if (time() - $parent_ping->{$_}->{last_time} > 1800) { # 30 minutes
            delete $parent_ping->{$_};
        }
    }

    return ($count, 1);
}

sub broadcast {
    my (%options) = @_;

    foreach my $pool_id (keys %$pools) {
        next if ($pools->{$pool_id}->{ready} != 1);

        $options{gorgone}->send_internal_message(
            identity => 'gorgone-proxy-' . $pool_id,
            action => $options{action},
            data => $options{data},
            token => $options{token}
        );
    }

    if (defined($httpserver->{ready}) && $httpserver->{ready} == 1) {
        $options{gorgone}->send_internal_message(
            identity => 'gorgone-proxy-httpserver',
            action => $options{action},
            data => $options{data},
            token => $options{token}
        );
    }
}

# Specific functions
sub pathway {
    my (%options) = @_;

    my $target = $options{target};
    if (!defined($target)) {
        $options{logger}->writeLogDebug('[proxy] need a valid node id');
        gorgone::standard::library::add_history({
            dbh => $options{dbh},
            code => GORGONE_ACTION_FINISH_KO, token => $options{token},
            data => { message => 'proxy - need a valid node id' },
            json_encode => 1
        });
        return -1;
    }

    if (!defined($register_nodes->{$target}) && !defined($register_subnodes->{$target})) {
        $options{logger}->writeLogDebug("[proxy] unknown target '$target'");
        gorgone::standard::library::add_history({
            dbh => $options{dbh},
            code => GORGONE_ACTION_FINISH_KO, token => $options{token},
            data => { message => 'proxy - unknown target ' . $target },
            json_encode => 1
        });
        return -1;
    }

    my @targets = ();
    if (defined($register_nodes->{$target})) {
        push @targets, $target;
    }
    if (defined($register_subnodes->{$target}->{static})) {
        push @targets, sort { $register_subnodes->{$target}->{static}->{$a} <=> $register_subnodes->{$target}->{static}->{$b} } keys %{$register_subnodes->{$target}->{static}};
    }
    if (defined($register_subnodes->{$target}->{dynamic})) {
        push @targets, keys %{$register_subnodes->{$target}->{dynamic}};
    }

    my $first_target;
    foreach (@targets) {
        if ($register_nodes->{$_}->{type} =~ /^(?:pull|wss|pullwss)$/ && !defined($register_nodes->{$_}->{identity})) {
            $options{logger}->writeLogDebug("[proxy] skip node " . $register_nodes->{$_}->{type} . " target '$_' for node '$target' - never connected");
            next;
        }

        # we let passthrough. it's for control channel
        if ($options{action} =~ /^(?:PING|PROXYADDNODE|PROXYDELNODE|PROXYADDSUBNODE|PROXYCLOSECONNECTION|PROXYSTOPREADCHANNEL)$/ && $_ eq $target) {
            return (1, 1, $_ . '~~' . $target, $_, $target);
        }

        if (!defined($last_pong->{$_}) || $last_pong->{$_} == 0 || (time() - $config->{pong_discard_timeout} < $last_pong->{$_})) {
            $options{logger}->writeLogDebug("[proxy] choose node target '$_' for node '$target'");
            return (1, 0, $_ . '~~' . $target, $_, $target);
        }

        $first_target = $_ if (!defined($first_target));
        if ($synctime_nodes->{$_}->{channel_read_stop} == 0) {
            $synctime_nodes->{$_}->{channel_read_stop} = 1;
            routing(
                target => $_,
                action => 'PROXYSTOPREADCHANNEL',
                frame => gorgone::class::frame->new(data => { id => $_ }),
                gorgone => $options{gorgone},
                dbh => $options{dbh},
                logger => $options{logger}
            );
        }
    }

    if (!defined($first_target)) {
        $options{logger}->writeLogDebug("[proxy] no pathway for target '$target'");
        gorgone::standard::library::add_history({
            dbh => $options{dbh},
            code => GORGONE_ACTION_FINISH_KO, token => $options{token},
            data => { message => 'proxy - no pathway for target ' . $target },
            json_encode => 1
        });
        return -1;
    }

    # if there are here, we use the first pathway (because all pathways had an issue)
    return (1, 0, $first_target . '~~' . $target, $first_target, $target);
}

sub setlogs {
    my (%options) = @_;

    if (!defined($options{data}->{data}->{id}) || $options{data}->{data}->{id} eq '') {
        gorgone::standard::library::add_history({
            dbh => $options{dbh},
            code => GORGONE_ACTION_FINISH_KO, token => $options{token},
            data => { message => 'proxy - need a id to setlogs' },
            json_encode => 1
        });
        return undef;
    }
    if ($synctime_nodes->{ $options{data}->{data}->{id} }->{in_progress} == 0) {
        gorgone::standard::library::add_history({
            dbh => $options{dbh},
            code => GORGONE_ACTION_FINISH_KO, token => $options{token},
            data => { message => 'proxy - skip setlogs response. Maybe too much time to get response. Retry' },
            json_encode => 1
        });
        return undef;
    }

    $options{logger}->writeLogInfo("[proxy] Received setlogs for '$options{data}->{data}->{id}'");

    # we have received the setlogs (it's like a pong response. not a problem if we received the pong after)
    $constatus_ping->{ $options{data}->{data}->{id} }->{in_progress_ping} = 0;
    $constatus_ping->{ $options{data}->{data}->{id} }->{ping_timeout} = 0;
    $constatus_ping->{ $options{data}->{data}->{id} }->{last_ping_recv} = time();
    $last_pong->{ $options{data}->{data}->{id} } = time() if (defined($last_pong->{ $options{data}->{data}->{id} }));

    $synctime_nodes->{ $options{data}->{data}->{id} }->{in_progress} = 0;

    my $ctime_recent = 0;
    # Transaction. We don't use last_id (problem if it's clean the sqlite table).
    my $status;
    $status = $options{dbh}->transaction_mode(1);
    return -1 if ($status == -1);

    foreach (@{$options{data}->{data}->{result}}) {
        # wrong timestamp inserted. we skip it
        if ($_->{ctime} !~ /[0-9\.]/) {
            $options{logger}->writeLogDebug("[proxy] wrong ctime for '$options{data}->{data}->{id}'");
            next;
        }
        $status = gorgone::standard::library::add_history({
            dbh => $options{dbh},
            etime => $_->{etime}, 
            code => $_->{code}, 
            token => $_->{token},
            instant => $_->{instant},
            data => $_->{data}
        });
        last if ($status == -1);
        $ctime_recent = $_->{ctime} if ($ctime_recent < $_->{ctime});
    }
    if ($status == 0 && update_sync_time(dbh => $options{dbh}, id => $options{data}->{data}->{id}, ctime => $ctime_recent) == 0) {
        $status = $options{dbh}->commit();
        return -1 if ($status == -1);
        $options{dbh}->transaction_mode(0);

        $synctime_nodes->{ $options{data}->{data}->{id} }->{ctime} = $ctime_recent if ($ctime_recent != 0); 
    } else {
        $options{dbh}->rollback();
        $options{dbh}->transaction_mode(0);
        return -1;
    }

    # We try to send it to parents
    foreach (keys %$parent_ping) {
        gorgone::class::core::send_message_parent(
            router_type => $parent_ping->{$_}->{router_type},
            identity => $_,
            response_type => 'SYNCLOGS',
            data => { id => $core_id },
            code => GORGONE_ACTION_BEGIN,
            token => undef,
        );
    }

    return 0;
}

sub ping_send {
    my (%options) = @_;

    my $nodes_id = [keys %$register_nodes];
    $nodes_id = [$options{node_id}] if (defined($options{node_id}));
    my $current_time = time();
    foreach my $id (@$nodes_id) {
        next if ($constatus_ping->{$id}->{in_progress_ping} == 1 || $current_time < $constatus_ping->{$id}->{next_ping});

        $constatus_ping->{$id}->{last_ping_sent} = $current_time;
        $constatus_ping->{$id}->{next_ping} = $current_time + $ping_interval;
        if ($register_nodes->{$id}->{type} eq 'push_zmq' || $register_nodes->{$id}->{type} eq 'push_ssh') {
            $constatus_ping->{$id}->{in_progress_ping} = 1;
            routing(action => 'PING', target => $id, frame => gorgone::class::frame->new(data => {}), gorgone => $options{gorgone}, dbh => $options{dbh}, logger => $options{logger});
        } elsif ($register_nodes->{$id}->{type} =~ /^(?:pull|wss|pullwss)$/) {
            $constatus_ping->{$id}->{in_progress_ping} = 1;
            $constatus_ping->{$id}->{in_progress_ping_pull} = time();
            routing(action => 'PING', target => $id, frame => gorgone::class::frame->new(data => {}), gorgone => $options{gorgone}, dbh => $options{dbh}, logger => $options{logger});
        }
    }
}

sub synclog {
    my (%options) = @_;

    # We check if we need synclogs
    if ($stop == 0) {
        $synctime_lasttime = time();
        full_sync_history(gorgone => $options{gorgone}, dbh => $options{dbh}, logger => $options{logger});
    }
}

sub full_sync_history {
    my (%options) = @_;
    
    foreach my $id (keys %{$register_nodes}) {
        if ($register_nodes->{$id}->{type} eq 'push_zmq') {
            routing(action => 'GETLOG', target => $id, frame => gorgone::class::frame->new(data => {}), gorgone => $options{gorgone}, dbh => $options{dbh}, logger => $options{logger});
        } elsif ($register_nodes->{$id}->{type} =~ /^(?:pull|wss|pullwss)$/) {
            routing(action => 'GETLOG', target => $id, frame => gorgone::class::frame->new(data => {}), gorgone => $options{gorgone}, dbh => $options{dbh}, logger => $options{logger});
        }
    }
}

sub update_sync_time {
    my (%options) = @_;
    
    # Nothing to update (no insert before)
    return 0 if ($options{ctime} == 0);

    my ($status) = $options{dbh}->query({
            query => "REPLACE INTO gorgone_synchistory (`id`, `ctime`) VALUES (?, ?)",
            bind_values => [$options{id}, $options{ctime}]
        }
    );
    return $status;
}

sub get_sync_time {
    my (%options) = @_;

    my ($status, $sth) = $options{dbh}->query({ query => "SELECT * FROM gorgone_synchistory WHERE id = '" . $options{node_id} . "'" });
    if ($status == -1) {
        $synctime_nodes->{$options{node_id}}->{synctime_error} = -1; 
        return -1;
    }

    $synctime_nodes->{$options{node_id}}->{synctime_error} = 0;
    if (my $row = $sth->fetchrow_hashref()) {
        $synctime_nodes->{ $row->{id} }->{ctime} = $row->{ctime};
        $synctime_nodes->{ $row->{id} }->{in_progress} = 0;
        $synctime_nodes->{ $row->{id} }->{in_progress_time} = -1;
    }

    return 0;
}

sub is_all_proxy_ready {
    my $ready = 0;
    for my $pool_id (1..$config->{pool}) {
        if (defined($pools->{$pool_id}) && $pools->{$pool_id}->{ready} == 1) {
            $ready++;
        }
    }

    return ($ready * 100 / $config->{pool});
}

sub rr_pool {
    my (%options) = @_;

    while (1) {
        $rr_current = $rr_current % $config->{pool};
        if ($pools->{$rr_current + 1}->{ready} == 1) {
            $rr_current++;
            return $rr_current;
        }
        $rr_current++;
    }
}

sub create_child {
    my (%options) = @_;

    if (!defined($core_id) || $core_id =~ /^\s*$/) {
        $options{logger}->writeLogError("[proxy] Cannot create child, need a core id");
        return ;
    }

    $options{logger}->writeLogInfo("[proxy] Create module 'proxy' child process for pool id '" . $options{pool_id} . "'");
    my $child_pid = fork();
    if ($child_pid == 0) {
        $0 = 'gorgone-proxy';
        my $module = gorgone::modules::core::proxy::class->new(
            logger => $options{logger},
            module_id => NAME,
            config_core => $config_core,
            config => $config,
            pool_id => $options{pool_id},
            core_id => $core_id,
            container_id => $options{pool_id}
        );
        $module->run();
        exit(0);
    }
    $options{logger}->writeLogDebug("[proxy] PID $child_pid (gorgone-proxy) for pool id '" . $options{pool_id} . "'");
    $pools->{$options{pool_id}} = { pid => $child_pid, ready => 0, running => 1 };
    $pools_pid->{$child_pid} = $options{pool_id};
}

sub create_httpserver_child {
    my (%options) = @_;

    $options{logger}->writeLogInfo("[proxy] Create module 'proxy' httpserver child process");

    my $rv = gorgone::standard::misc::mymodule_load(
        logger => $options{logger},
        module => 'gorgone::modules::core::proxy::httpserver',
        error_msg => "Cannot load module 'gorgone::modules::core::proxy::httpserver'"
    );
    return if ($rv != 0);

    my $child_pid = fork();
    if ($child_pid == 0) {
        $0 = 'gorgone-proxy-httpserver';
        my $module = gorgone::modules::core::proxy::httpserver->construct(
            logger => $options{logger},
            module_id => NAME,
            config_core => $config_core,
            config => $config,
            container_id => 'httpserver'
        );
        $module->run();
        exit(0);
    }
    $options{logger}->writeLogDebug("[proxy] PID $child_pid (gorgone-proxy-httpserver)");
    $httpserver = { pid => $child_pid, ready => 0, running => 1 };
}

sub pull_request {
    my (%options) = @_;

    my $message = gorgone::standard::library::build_protocol(
        action => $options{action},
        raw_data_ref => $options{raw_data_ref},
        token => $options{token},
        target => $options{target}
    );

    if (!defined($register_nodes->{ $options{target_parent} }->{identity})) {
        gorgone::standard::library::add_history({
            dbh => $options{dbh},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => "proxy - node '" . $options{target_parent} . "' had never been connected" },
            json_encode => 1
        });
        return undef;
    }

    my $identity = unpack('H*', $register_nodes->{ $options{target_parent} }->{identity});
    my ($rv, $cipher_infos) = $options{gorgone}->is_handshake_done(
        identity => $identity
    );
    if ($rv == 0) {
        gorgone::standard::library::add_history({
            dbh => $options{dbh},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => "proxy - node '" . $options{target_parent} . "' had never been connected" },
            json_encode => 1
        });
        return undef;
    }

    $options{gorgone}->external_core_response(
        cipher_infos => $cipher_infos,
        identity => $identity,
        message => $message
    );
}

sub get_constatus_result {
    my (%options) = @_;

    return $constatus_ping;
}

sub unregister_nodes {
    my (%options) = @_;

    return if (!defined($options{data}->{nodes}));

    foreach my $node (@{$options{data}->{nodes}}) {
        if (defined($register_nodes->{ $node->{id} }) && $register_nodes->{ $node->{id} }->{type} !~ /^(?:pull|wss|pullwss)$/) {
            routing(
                action => 'PROXYDELNODE',
                target => $node->{id},
                frame => gorgone::class::frame->new(data => $node),
                gorgone => $options{gorgone},
                dbh => $options{dbh},
                logger => $options{logger}
            );
        }

        my $prevail = 0;
        $prevail = 1  if (defined($prevails->{ $node->{id} }));

        if (defined($register_nodes->{ $node->{id} }) && $register_nodes->{ $node->{id} }->{type} =~ /^(?:pull|wss|pullwss)$/ && $prevail == 1) {
            $register_nodes->{ $node->{id} }->{identity} = undef;
        }

        $options{logger}->writeLogInfo("[proxy] Node '" . $node->{id} . "' is unregistered");
        if (defined($register_nodes->{ $node->{id} }) && $register_nodes->{ $node->{id} }->{nodes}) {
            foreach my $subnode (@{$register_nodes->{ $node->{id} }->{nodes}}) {
                delete $register_subnodes->{ $subnode->{id} }->{static}->{ $node->{id} }
                    if (defined($register_subnodes->{ $subnode->{id} }->{static}->{ $node->{id} }) && $prevail == 0);
                delete $register_subnodes->{ $subnode->{id} }->{dynamic}->{ $node->{id} }
                    if (defined($register_subnodes->{ $subnode->{id} }->{dynamic}->{ $node->{id} }));
            }
        }

        delete $nodes_pool->{ $node->{id} } if (defined($nodes_pool->{ $node->{id} }));
        if (defined($register_nodes->{ $node->{id} })) {
            delete $register_nodes->{ $node->{id} } if ($prevail == 0);
            delete $synctime_nodes->{ $node->{id} };
            delete $constatus_ping->{ $node->{id} };
            delete $last_pong->{ $node->{id} };
        }
    }
}

# It comes from PONG result. 
sub register_subnodes {
    my (%options) = @_;

    # we remove dynamic values
    foreach my $subnode_id (keys %$register_subnodes) {
        delete $register_subnodes->{$subnode_id}->{dynamic}->{ $options{id} }
            if (defined($register_subnodes->{$subnode_id}->{dynamic}->{ $options{id} }));
    }

    # we can add in dynamic even if it's in static (not an issue)
    my $subnodes = [$options{subnodes}];
    while (1) {
        last if (scalar(@$subnodes) <= 0);

        my $entry = shift(@$subnodes);
        foreach (keys %$entry) {
            $register_subnodes->{$_}->{dynamic}->{ $options{id} } = 1;
        }
        push @$subnodes, $entry->{nodes} if (defined($entry->{nodes}));
    }
}

# 'pull' type:
#    - it does a REGISTERNODES without subnodes (if it already exist, no new entry created, otherwise create an entry). We save the uniq identity
#    - PING done by proxy and with PONG we get subnodes
sub register_nodes {
    my (%options) = @_;

    return if (!defined($options{data}->{nodes}));

    foreach my $node (@{$options{data}->{nodes}}) {
        my ($new_node, $prevail) = (1, 0);

        # prevail = 1 means: we cannot override the old one (if it exists)
        if (defined($prevails_subnodes->{ $node->{id} })) {
            $options{logger}->writeLogInfo("[proxy] cannot register node '$node->{id}': already defined as a subnode [prevails]");
            next;
        }
        $prevail = 1 if (defined($prevails->{ $node->{id} }));
        $prevails->{ $node->{id} } = 1 if (defined($node->{prevail}) && $node->{prevail} == 1);

        if ($prevail == 1) {
            $options{logger}->writeLogInfo("[proxy] cannot override node '$node->{id}' registration: prevails!!!");
        }

        if (defined($register_nodes->{ $node->{id} }) && $prevail == 0) {
            # we remove subnodes before
            foreach my $subnode_id (keys %$register_subnodes) {
                delete $register_subnodes->{$subnode_id}->{static}->{ $node->{id} }
                    if (defined($register_subnodes->{$subnode_id}->{static}->{ $node->{id} }));
                delete $register_subnodes->{$subnode_id}->{dynamic}->{ $node->{id} }
                    if (defined($register_subnodes->{$subnode_id}->{dynamic}->{ $node->{id} }));
            }
        }

        if (defined($register_nodes->{ $node->{id} })) {
            $new_node = 0;

            if ($register_nodes->{ $node->{id} }->{type} !~ /^(?:pull|wss|pullwss)$/ && $node->{type} =~ /^(?:pull|wss|pullwss)$/) {
                unregister_nodes(
                    data => { nodes => [ { id => $node->{id} } ] },
                    gorgone => $options{gorgone},
                    dbh => $options{dbh},
                    logger => $options{logger}
                );
                $new_node = 1;
            }
        }

        if ($prevail == 0) {
            $register_nodes->{ $node->{id} } = $node;
            if (defined($node->{nodes})) {
                foreach my $subnode (@{$node->{nodes}}) {
                    $register_subnodes->{ $subnode->{id} } = { static => {}, dynamic => {} } if (!defined($register_subnodes->{ $subnode->{id} }));
                    $register_subnodes->{ $subnode->{id} }->{static}->{ $node->{id} } = defined($subnode->{pathscore}) && $subnode->{pathscore} =~ /[0-9]+/ ? $subnode->{pathscore} : 1;

                    # subnodes also prevails. we try to unregister it
                    if (defined($node->{prevail}) && $node->{prevail} == 1) {
                        unregister_nodes(
                            data => { nodes => [ { id => $subnode->{id} } ] },
                            gorgone => $options{gorgone},
                            dbh => $options{dbh},
                            logger => $options{logger}
                        );
                        $prevails_subnodes->{ $subnode->{id} } = 1;
                    }
                }
            }
        }

        # we update identity in all cases (already created or not)
        if ($node->{type} =~ /^(?:pull|wss|pullwss)$/ && defined($node->{identity})) {
            $register_nodes->{ $node->{id} }->{identity} = $node->{identity};
            $last_pong->{ $node->{id} } = time() if (defined($last_pong->{ $node->{id} }));
        }

        $last_pong->{ $node->{id} } = 0 if (!defined($last_pong->{ $node->{id} }));
        if (!defined($synctime_nodes->{ $node->{id} })) {
            $synctime_nodes->{ $node->{id} } = {
                ctime => 0,
                in_progress => 0,
                in_progress_time => -1,
                synctime_error => 0,
                channel_read_stop => 0,
                channel_ready => 0
            };
            get_sync_time(node_id => $node->{id}, dbh => $options{dbh});
        }

        if ($register_nodes->{ $node->{id} }->{type} !~ /^(?:pull|wss|pullwss)$/) {
            if ($prevail == 1) {
                routing(
                    action => 'PROXYADDNODE',
                    target => $node->{id},
                    frame => gorgone::class::frame->new(data => $register_nodes->{ $node->{id} }),
                    gorgone => $options{gorgone},
                    dbh => $options{dbh},
                    logger => $options{logger}
                );
            } else {
                routing(
                    action => 'PROXYADDNODE',
                    target => $node->{id},
                    frame => gorgone::class::frame->new(data => $node),
                    gorgone => $options{gorgone},
                    dbh => $options{dbh},
                    logger => $options{logger}
                );
            }
        }
        if ($new_node == 1) {
            $constatus_ping->{ $node->{id} } = {
                type => $node->{type},
                in_progress_ping => 0,
                ping_timeout => 0,
                last_ping_sent => 0,
                last_ping_recv => 0,
                next_ping => time() + int(rand($ping_interval)),
                ping_ok => 0,
                ping_failed => 0,
                nodes => {}
            };
            $options{logger}->writeLogInfo("[proxy] Node '" . $node->{id} . "' is registered");
        }
    }
}

sub prepare_remote_copy {
    my (%options) = @_;

    my @actions = ();

    if (!defined($options{data}->{content}->{source}) || $options{data}->{content}->{source} eq '') {
        $options{logger}->writeLogError('[proxy] Need source for remote copy');
        gorgone::standard::library::add_history({
            dbh => $options{dbh},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => 'remote copy failed' },
            json_encode => 1
        });
        return -1;
    }
    if (!defined($options{data}->{content}->{destination}) || $options{data}->{content}->{destination} eq '') {
        $options{logger}->writeLogError('[proxy] Need destination for remote copy');
        gorgone::standard::library::add_history({
            dbh => $options{dbh},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => 'remote copy failed' },
            json_encode => 1
        });
        return -1;
    }

    my $type;
    my $filename;
    my $localsrc = $options{data}->{content}->{source};
    my $src = $options{data}->{content}->{source};
    my $dst = $options{data}->{content}->{destination};

    if (-f $options{data}->{content}->{source}) {
        $type = 'regular';
        $localsrc = $src;
        $filename = File::Basename::basename($src);
        $dst .= $filename if ($dst =~ /\/$/);
    } elsif (-d $options{data}->{content}->{source}) {
        $type = 'archive';
        $filename = (defined($options{data}->{content}->{type}) ? $options{data}->{content}->{type} : 'tmp') . '-' . $options{target} . '.tar.gz';
        $localsrc = $options{data}->{content}->{cache_dir} . '/' . $filename;

        my $tar = Archive::Tar->new();
        unless (chdir($options{data}->{content}->{source})) {
            $options{logger}->writeLogError("[proxy] cannot chdir: $!");
            gorgone::standard::library::add_history({
                dbh => $options{dbh},
                code => GORGONE_ACTION_FINISH_KO,
                token => $options{token},
                data => { message => "cannot chdir: $!" },
                json_encode => 1
            });
            return -1;
        }

        my @inventory = ();
        File::Find::find ({ wanted => sub { push @inventory, $_ }, no_chdir => 1 }, '.');
        my $owner;
        $owner = $options{data}->{content}->{owner} if (defined($options{data}->{content}->{owner}) && $options{data}->{content}->{owner} ne '');
        my $group;
        $group = $options{data}->{content}->{group} if (defined($options{data}->{content}->{group}) && $options{data}->{content}->{group} ne '');
        foreach my $file (@inventory) {
            next if ($file eq '.');
            $tar->add_files($file);
            if (defined($owner) || defined($group)) {
                $tar->chown($file, $owner, $group);
            }
        }

        unless (chdir($options{data}->{content}->{cache_dir})) {
            $options{logger}->writeLogError("[proxy] cannot chdir: $!");
            gorgone::standard::library::add_history({
                dbh => $options{dbh},
                code => GORGONE_ACTION_FINISH_KO,
                token => $options{token},
                data => { message => "cannot chdir: $!" },
                json_encode => 1
            });
            return -1;
        }
        unless ($tar->write($filename, COMPRESS_GZIP)) {
            $options{logger}->writeLogError("[proxy] Tar failed: " . $tar->error());
            gorgone::standard::library::add_history({
                dbh => $options{dbh},
                code => GORGONE_ACTION_FINISH_KO,
                token => $options{token},
                data => { message => 'tar failed' },
                json_encode => 1
            });
            return -1;
        }
    } else {
        $options{logger}->writeLogError('[proxy] Unknown source for remote copy');
        gorgone::standard::library::add_history({
            dbh => $options{dbh},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => 'unknown source' },
            json_encode => 1
        });
        return -1;
    }

    sysopen(FH, $localsrc, O_RDONLY);
    binmode(FH);
    my $buffer_size = (defined($config->{buffer_size})) ? $config->{buffer_size} : 500_000;
    my $buffer;
    while (my $bytes = sysread(FH, $buffer, $buffer_size)) {
        my $action = JSON::XS->new->encode({
            logging => $options{data}->{logging},
            content => {
                status => 'inprogress',
                type => $type,
                chunk => {
                    data => MIME::Base64::encode_base64($buffer),
                    size => $bytes,
                },
                md5 => undef,
                destination => $dst,
                cache_dir => $options{data}->{content}->{cache_dir}
            },
            parameters => { no_fork => 1 }
        });
        push @actions, \$action;
    }
    close FH;

    my $action = JSON::XS->new->encode({
        logging => $options{data}->{logging},
        content => {
            status => 'end',
            type => $type,
            chunk => undef,
            md5 => file_md5_hex($localsrc),
            destination => $dst,
            cache_dir => $options{data}->{content}->{cache_dir},
            owner => $options{data}->{content}->{owner},
            group => $options{data}->{content}->{group}
        },
        parameters => { no_fork => 1 }
    });
    push @actions, \$action;

    return (0, \@actions);
}

sub setcoreid {
    my (%options) = @_;

    $core_id = $options{core_id};
    check_create_child(%options);
}

sub add_parent_ping {
    my (%options) = @_;

    $options{logger}->writeLogDebug("[proxy] Parent ping '" . $options{identity} . "' is registered");
    $parent_ping->{ $options{identity} } = { last_time => time(), router_type => $options{router_type} };
}

1;
