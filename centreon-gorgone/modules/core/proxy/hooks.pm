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

package modules::core::proxy::hooks;

use warnings;
use strict;
use JSON::XS;
use centreon::script::gorgonecore;
use centreon::gorgone::common;
use modules::core::proxy::class;

my $NAME = 'proxy';
my $EVENTS = [
    { event => 'PROXYREADY' },
    { event => 'SETLOGS' }, # internal. Shouldn't be used by third party clients
    { event => 'PONG' }, # internal. Shouldn't be used by third party clients
    { event => 'REGISTERNODES' }, # internal. Shouldn't be used by third party clients
    { event => 'UNREGISTERNODES' }, # internal. Shouldn't be used by third party clients
    { event => 'PROXYADDNODE' }, # internal. Shouldn't be used by third party clients
    { event => 'PROXYDELNODE' }, # internal. Shouldn't be used by third party clients
    { event => 'PROXYADDSUBNODE' }, # internal. Shouldn't be used by third party clients
    { event => 'PONGRESET' }, # internal. Shouldn't be used by third party clients
];

my $config_core;
my $config;

my $synctime_error = 0;
my $synctime_nodes = {}; # get last time retrieved
my $synctime_lasttime;
my $synctime_option;
my $synctimeout_option;
my $ping_option;
my $ping_time = 0;

my $last_pong = {}; 
my $register_nodes = {};
my $register_subnodes = {};
my $constatus_ping = {};
my $parent_ping = {};
my $pools = {};
my $pools_pid = {};
my $nodes_pool = {};
my $rr_current = 0;
my $stop = 0;
my ($external_socket, $internal_socket, $core_id);

sub register {
    my (%options) = @_;
    
    $config = $options{config};
    $config_core = $options{config_core};
    return ($NAME, $EVENTS);
}

sub init {
    my (%options) = @_;

    $synctime_lasttime = time();
    $synctime_option = defined($config->{synchistory_time}) ? $config->{synchistory_time} : 300;
    $synctimeout_option = defined($config->{synchistory_timeout}) ? $config->{synchistory_timeout} : 120;
    $ping_option = defined($config->{ping}) ? $config->{ping} : 60;
    
    $core_id = $options{id};
    $external_socket = $options{external_socket};
    $internal_socket = $options{internal_socket};
    for my $pool_id (1..$config->{pool}) {
        create_child(pool_id => $pool_id, logger => $options{logger});
    }
}

sub routing {
    my (%options) = @_;

    my $data;
    eval {
        $data = JSON::XS->new->utf8->decode($options{data});
    };
    if ($@) {
        $options{logger}->writeLogError("[proxy] -hooks- Cannot decode json data: $@");
        centreon::gorgone::common::add_history(
            dbh => $options{dbh},
            code => 20, token => $options{token},
            data => { message => 'proxy - cannot decode json' },
            json_encode => 1
        );
        return undef;
    }

    if ($options{action} eq 'PONG') {
        return undef if (!defined($data->{data}->{id}) || $data->{data}->{id} eq '');
        $synctime_nodes->{$data->{data}->{id}}->{in_progress_ping} = 0;
        $last_pong->{$data->{data}->{id}} = time();
        $constatus_ping->{$data->{data}->{id}}->{last_ping_recv} = time();
        $constatus_ping->{$data->{data}->{id}}->{nodes} = $data->{data}->{data};
        register_subnodes(%options, id => $data->{data}->{id}, subnodes => $data->{data}->{data});
        $options{logger}->writeLogInfo("[proxy] -hooks- Pong received from '" . $data->{data}->{id} . "'");
        return undef;
    }

    if ($options{action} eq 'PONGRESET') {
        return undef if (!defined($data->{id}) || $data->{id} eq '');
        $synctime_nodes->{$data->{id}}->{in_progress_ping} = 0;
        $options{logger}->writeLogInfo("[proxy] -hooks- PongReset received from '" . $data->{id} . "'");
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
        $pools->{$data->{pool_id}}->{ready} = 1;
        return undef;
    }
    
    if ($options{action} eq 'SETLOGS') {
        setlogs(dbh => $options{dbh}, data => $data, token => $options{token}, logger => $options{logger});
        return undef;
    }

    if (!defined($options{target}) || 
        (!defined($register_subnodes->{$options{target}}) && !defined($register_nodes->{$options{target}}))) {
        centreon::gorgone::common::add_history(
            dbh => $options{dbh},
            code => centreon::gorgone::module::ACTION_FINISH_KO, token => $options{token},
            data => { message => 'proxy - need a valid node id' },
            json_encode => 1
        );
        return undef;
    }
    
    if ($options{action} eq 'GETLOG') {
        if (defined($register_nodes->{$options{target}}) && $register_nodes->{$options{target}}->{type} eq 'push_ssh') {
            centreon::gorgone::common::add_history(
                dbh => $options{dbh},
                code => centreon::gorgone::module::ACTION_FINISH_KO, token => $options{token},
                data => { message => "proxy - can't get log a ssh target" },
                json_encode => 1
            );
            return undef;
        }

        if (defined($register_nodes->{$options{target}})) {
            if ($synctime_nodes->{$options{target}}->{synctime_error} == -1 || get_sync_time(dbh => $options{dbh}, node_id => $options{target}) == -1) {
                centreon::gorgone::common::add_history(
                    dbh => $options{dbh},
                    code => centreon::gorgone::module::ACTION_FINISH_KO, token => $options{token},
                    data => { message => 'proxy - problem to getlog' },
                    json_encode => 1
                );
                return undef;
            }

            if ($synctime_nodes->{$options{target}}->{in_progress} == 1) {
                centreon::gorgone::common::add_history(
                    dbh => $options{dbh},
                    code => centreon::gorgone::module::ACTION_FINISH_KO, token => $options{token},
                    data => { message => 'proxy - getlog already in progress' },
                    json_encode => 1
                );
                return undef;
            }

            # We put the good time to get        
            my $ctime = $synctime_nodes->{$options{target}}->{ctime};
            my $last_id = $synctime_nodes->{$options{target}}->{last_id};
            $options{data} = centreon::gorgone::common::json_encode(data => { ctime => $ctime, id => $last_id });
            $synctime_nodes->{$options{target}}->{in_progress} = 1;
            $synctime_nodes->{$options{target}}->{in_progress_time} = time();
        }
    }

    my $target = $options{target};
    # we prefer to use direct target
    if (!defined($register_nodes->{$options{target}})) {
        $target = $register_subnodes->{$options{target}};
    }

    # Mode zmq pull
    if ($register_nodes->{$target}->{type} eq 'pull') {
        pull_request(%options, data_decoded => $data);
        return undef;
    }
    
    if (centreon::script::gorgonecore::waiting_ready_pool(pool => $pools) == 0) {
        centreon::gorgone::common::add_history(
            dbh => $options{dbh},
            code => centreon::gorgone::module::ACTION_FINISH_KO, token => $options{token},
            data => { message => 'proxy - still none ready' },
            json_encode => 1
        );
        return undef;
    }

    my $identity;
    if (defined($nodes_pool->{$target})) {
        $identity = $nodes_pool->{$target};
    } else {
        $identity = rr_pool();
        $nodes_pool->{$target} = $identity;
    }

    centreon::gorgone::common::zmq_send_message(
        socket => $options{socket}, identity => 'gorgoneproxy-' . $identity,
        action => $options{action}, data => $options{data}, token => $options{token},
        target => $options{target}
    );
}

sub gently {
    my (%options) = @_;

    $stop = 1;
    foreach my $pool_id (keys %{$pools}) {
        $options{logger}->writeLogInfo("[proxy] -hooks- Send TERM signal for pool '" . $pool_id . "'");
        if ($pools->{$pool_id}->{running} == 1) {
            CORE::kill('TERM', $pools->{$pool_id}->{pid});
        }
    }
}

sub kill {
    my (%options) = @_;

    foreach (keys %{$pools}) {
        if ($pools->{$_}->{running} == 1) {
            $options{logger}->writeLogInfo("[proxy] -hooks- Send KILL signal for pool '" . $_ . "'");
            CORE::kill('KILL', $pools->{$_}->{pid});
        }
    }
}

sub kill_internal {
    my (%options) = @_;

}

sub check {
    my (%options) = @_;

    my $count = 0;
    foreach my $pid (keys %{$options{dead_childs}}) {
        # Not me
        next if (!defined($pools_pid->{$pid}));
        
        # If someone dead, we recreate
        my $pool_id = $pools_pid->{$pid};
        delete $pools->{$pools_pid->{$pid}};
        delete $pools_pid->{$pid};
        delete $options{dead_childs}->{$pid};
        if ($stop == 0) {
            create_child(pool_id => $pool_id, logger => $options{logger});
        }
    }
    
    foreach (keys %{$pools}) {
        $count++  if ($pools->{$_}->{running} == 1);
    }
    
    # We put synclog request in timeout
    foreach (keys %{$synctime_nodes}) {
        if ($synctime_nodes->{$_}->{in_progress} == 1 && 
            time() - $synctime_nodes->{$_}->{in_progress_time} > $synctimeout_option) {
            centreon::gorgone::common::add_history(
                dbh => $options{dbh},
                code => centreon::gorgone::module::ACTION_FINISH_KO,
                data => { message => "proxy - getlog in timeout for '$_'" },
                json_encode => 1
            );
            $synctime_nodes->{$_}->{in_progress} = 0;
        }
    }
    
    # We check if we need synclogs
    if ($stop == 0 &&
        time() - $synctime_lasttime > $synctime_option) {
        $synctime_lasttime = time();
        full_sync_history(dbh => $options{dbh});
    }
    
    if ($stop == 0 &&
        time() - $ping_time > $ping_option) {
        $options{logger}->writeLogInfo("[proxy] -hooks- Send pings");
        $ping_time = time();
        ping_send(dbh => $options{dbh});
    }
    
    return $count;
}

# Specific functions
sub setlogs {
    my (%options) = @_;
    
    if (!defined($options{data}->{data}->{id}) || $options{data}->{data}->{id} eq '') {
        centreon::gorgone::common::add_history(
            dbh => $options{dbh},
            code => centreon::gorgone::module::ACTION_FINISH_KO, token => $options{token},
            data => { message => 'proxy - need a id to setlogs' },
            json_encode => 1
        );
        return undef;
    }
    if ($synctime_nodes->{$options{data}->{data}->{id}}->{in_progress} == 0) {
        centreon::gorgone::common::add_history(
            dbh => $options{dbh},
            code => centreon::gorgone::module::ACTION_FINISH_KO, token => $options{token},
            data => { message => 'proxy - skip setlogs response. Maybe too much time to get response. Retry' },
            json_encode => 1
        );
        return undef;
    }

    $options{logger}->writeLogInfo("[proxy] -hooks- Received setlogs for '$options{data}->{data}->{id}'");
    
    $synctime_nodes->{$options{data}->{data}->{id}}->{in_progress} = 0;
    
    my $ctime_recent = 0;
    my $last_id = 0;
    # Transaction
    $options{dbh}->transaction_mode(1);
    my $status = 0;
    foreach (keys %{$options{data}->{data}->{result}}) {
        $status = centreon::gorgone::common::add_history(
            dbh => $options{dbh},
            etime => $options{data}->{data}->{result}->{$_}->{etime}, 
            code => $options{data}->{data}->{result}->{$_}->{code}, 
            token => $options{data}->{data}->{result}->{$_}->{token},
            data => $options{data}->{data}->{result}->{$_}->{data}
        );
        last if ($status == -1);
        $ctime_recent = $options{data}->{data}->{result}->{$_}->{ctime} if ($ctime_recent < $options{data}->{data}->{result}->{$_}->{ctime});
        $last_id = $options{data}->{data}->{result}->{$_}->{id} if ($last_id < $options{data}->{data}->{result}->{$_}->{id});
    }
    if ($status == 0 && update_sync_time(dbh => $options{dbh}, id => $options{data}->{data}->{id}, last_id => $last_id, ctime => $ctime_recent) == 0) {
        $options{dbh}->commit();
        $synctime_nodes->{$options{data}->{data}->{id}}->{last_id} = $last_id if ($last_id != 0);
        $synctime_nodes->{$options{data}->{data}->{id}}->{ctime} = $ctime_recent if ($ctime_recent != 0);
    } else {
        $options{dbh}->rollback();
    }
    $options{dbh}->transaction_mode(0);
    
    # We try to send it to parents
    foreach (keys %{$parent_ping}) {
        use Data::Dumper; print Data::Dumper::Dumper($parent_ping);
        print Data::Dumper::Dumper($options{data});
        centreon::script::gorgonecore::send_message_parent(
            router_type => $parent_ping->{$_},
            identity => $_,
            response_type => 'SYNCLOGS',
            data => '{ "id": "' . $core_id . '"}',
            code => 0,
            token => $options{token}
        );
    }
}

sub ping_send {
    my (%options) = @_;

    my $nodes_id = [keys %$register_nodes];
    $nodes_id = [$options{node_id}] if (defined($options{node_id}));
    foreach my $id (@$nodes_id) {
        next if (defined($synctime_nodes->{$id}) && $synctime_nodes->{$id}->{in_progress_ping} == 1);

        $constatus_ping->{$id}->{last_ping_sent} = time();
        if ($register_nodes->{$id}->{type} eq 'push_zmq') {
            $synctime_nodes->{$id}->{in_progress_ping} = 1;
            routing(socket => $internal_socket, action => 'PING', target => $id, data => '{}', dbh => $options{dbh});
        } elsif ($register_nodes->{$id}->{type} eq 'pull') {
            $synctime_nodes->{$id}->{in_progress_ping} = 1;
            routing(action => 'PING', target => $id, data => '{}', dbh => $options{dbh});
        }
    }
}

sub synclog {
    my (%options) = @_;

    # We check if we need synclogs
    if ($stop == 0 &&
        time() - $synctime_lasttime > $synctime_option) {
        $synctime_lasttime = time();
        full_sync_history(dbh => $options{dbh});
    }
}

sub full_sync_history {
    my (%options) = @_;
    
    foreach my $id (keys %{$register_nodes}) {
        if ($register_nodes->{$id}->{type} eq 'push_zmq') {
            routing(socket => $internal_socket, action => 'GETLOG', target => $id, data => '{}', dbh => $options{dbh});
        } elsif ($register_nodes->{$id}->{type} eq 'pull') {
            routing(action => 'GETLOG', target => $id, data => '{}', dbh => $options{dbh});
        }
    }
}

sub update_sync_time {
    my (%options) = @_;
    
    # Nothing to update (no insert before)
    return 0 if ($options{ctime} == 0);

    my $status;
    if ($synctime_nodes->{$options{id}}->{last_id} == 0) {
        ($status) = $options{dbh}->query("INSERT INTO gorgone_synchistory (`id`, `ctime`, `last_id`) VALUES (" . $options{dbh}->quote($options{id}) . ", " . $options{dbh}->quote($options{ctime}) . ", " . $options{dbh}->quote($options{last_id}) . ")");
    } else {
        ($status) = $options{dbh}->query("UPDATE gorgone_synchistory SET `ctime` = " . $options{dbh}->quote($options{ctime}) . ", `last_id` = " . $options{dbh}->quote($options{last_id}) . " WHERE `id` = " . $options{dbh}->quote($options{id}));
    }
    return $status;
}

sub get_sync_time {
    my (%options) = @_;
    
    my ($status, $sth) = $options{dbh}->query("SELECT * FROM gorgone_synchistory WHERE id = '" . $options{node_id} . "'");
    if ($status == -1) {
        $synctime_nodes->{$options{node_id}}->{synctime_error} = -1; 
        return -1;
    }

    $synctime_nodes->{$options{node_id}}->{synctime_error} = 0;
    if (my $row = $sth->fetchrow_hashref()) {
        $synctime_nodes->{$row->{id}}->{ctime} = $row->{ctime};
        $synctime_nodes->{$row->{id}}->{in_progress} = 0;
        $synctime_nodes->{$row->{id}}->{in_progress_time} = -1;
        $synctime_nodes->{$row->{id}}->{last_id} = $row->{last_id};
    }
    
    return 0;
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
    
    $options{logger}->writeLogInfo("[proxy] -hooks- Create module 'proxy' child process for pool id '" . $options{pool_id} . "'");
    my $child_pid = fork();
    if ($child_pid == 0) {
        $0 = 'gorgone-proxy';
        my $module = modules::core::proxy::class->new(
            logger => $options{logger},
            module_id => $NAME,
            config_core => $config_core,
            config => $config,
            pool_id => $options{pool_id},
            core_id => $core_id
        );
        $module->run();
        exit(0);
    }
    $options{logger}->writeLogInfo("[proxy] -hooks- PID $child_pid (gorgone-proxy) for pool id '" . $options{pool_id} . "'");
    $pools->{$options{pool_id}} = { pid => $child_pid, ready => 0, running => 1 };
    $pools_pid->{$child_pid} = $options{pool_id};
}

sub pull_request {
    my (%options) = @_;

    # No target anymore. We remove it.
    my $message = centreon::gorgone::common::build_protocol(
        action => $options{action}, data => $options{data}, token => $options{token},
        target => ''
    );
    my ($status, $key) = centreon::gorgone::common::is_handshake_done(dbh => $options{dbh}, identity => unpack('H*', $options{target}));
    if ($status == 0) {
        centreon::gorgone::common::add_history(
            dbh => $options{dbh},
            code => 20, token => $options{token},
            data => { message => "proxy - node '" . $options{target} . "' had never been connected" },
            json_encode => 1
        );
        return undef;
    }
    
    # Should call here the function to transform data and do some put logs. A loop (because it will also be used in sub proxy process)
    # Catch some actions call and do some transformation (on file copy)
    # TODO
    
    centreon::gorgone::common::zmq_send_message(
        socket => $external_socket,
        cipher => $config_core->{cipher},
        vector => $config_core->{vector},
        symkey => $key,
        identity => $options{target},
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
        if ($node->{type} ne 'pull') {
            routing(socket => $internal_socket, action => 'PROXYDELNODE', target => $node->{id}, data => JSON::XS->new->utf8->encode($node), dbh => $options{dbh});
        }

        $options{logger}->writeLogInfo("[proxy] -hooks- Poller '" . $node->{id} . "' is unregistered");
        if (defined($register_nodes->{$node->{id}}) && $register_nodes->{$node->{id}}->{nodes}) {
            foreach my $subnode_id (@{$register_nodes->{$node->{id}}->{nodes}}) {
                delete $register_subnodes->{$subnode_id} 
                    if ($register_subnodes->{$subnode_id} eq $node->{id});
            }
        }

        if (defined($register_nodes->{$node->{id}})) {
            delete $register_nodes->{$node->{id}};
            delete $synctime_nodes->{$node->{id}};
            delete $constatus_ping->{$node->{id}};
        }
    }
}

sub register_subnodes {
    my (%options) = @_;

    foreach my $subnode_id (keys %$register_subnodes) {
        delete $register_subnodes->{$subnode_id}
            if ($register_subnodes->{$subnode_id} eq $options{id});
    }

    my $subnodes_register = { id => $options{id}, nodes => {} };
    my $subnodes = [$options{subnodes}];
    while (1) {
        last if (scalar(@$subnodes) <= 0);

        my $entry = shift(@$subnodes);
        foreach (keys %$entry) {
            $subnodes_register->{nodes}->{$_} = $options{id};
            $register_subnodes->{$_} = $options{id};
        }
        push @$subnodes, $entry->{nodes} if (defined($entry->{nodes}));
    }

    if ($register_nodes->{$options{id}}->{type} ne 'pull') {
        routing(
            socket => $internal_socket, 
            action => 'PROXYADDSUBNODE',
            target => $options{id}, 
            data => JSON::XS->new->utf8->encode($subnodes_register),
            dbh => $options{dbh}
        );
    }
}

sub register_nodes {
    my (%options) = @_;

    return if (!defined($options{data}->{nodes}));

    foreach my $node (@{$options{data}->{nodes}}) {
        my $new_node = 1;
        if (defined($register_nodes->{$node->{id}})) {
            $new_node = 0;
            # we remove subnodes before
            if ($register_nodes->{$node->{id}}->{type} ne 'push_ssh') {
                foreach my $subnode_id (keys %$register_subnodes) {
                     delete $register_subnodes->{$subnode_id}
                        if ($register_subnodes->{$subnode_id} eq $node->{id});
                }
            }
        }

        $register_nodes->{$node->{id}} = $node;
        if (defined($node->{nodes})) {
            foreach my $subnode_id (@{$node->{nodes}}) {
                $register_subnodes->{$subnode_id} = $node->{id};
            }
        }

        if ($node->{type} eq 'push_zmq' || $node->{type} eq 'pull') {
            $last_pong->{$node->{id}} = 0 if (!defined($last_pong->{$node->{id}}));
            if (!defined($synctime_nodes->{$node->{id}})) {
                $synctime_nodes->{$node->{id}} = { ctime => 0, in_progress_ping => 0, in_progress => 0, in_progress_time => -1, last_id => 0, synctime_error => 0 };
                get_sync_time(node_id => $node->{id}, dbh => $options{dbh});
            }
        }

        if ($node->{type} ne 'pull') {
            routing(socket => $internal_socket, action => 'PROXYADDNODE', target => $node->{id}, data => JSON::XS->new->utf8->encode($node), dbh => $options{dbh});
        }
        if ($new_node == 1) {
            $constatus_ping->{$node->{id}} = { type => $node->{type}, last_ping_sent => 0, last_ping_recv => 0, nodes => {} };
            # we provide information to the proxy class
            ping_send(node_id => $node->{id}, dbh => $options{dbh});
            $options{logger}->writeLogInfo("[proxy] -hooks- Poller '" . $node->{id} . "' is registered");
        }
    }
}

sub add_parent_ping {
    my (%options) = @_;

    $options{logger}->writeLogDebug("[proxy] -hooks- parent ping '" . $options{identity} . "' is registered");
    $parent_ping->{$options{identity}} = $options{router_type};
}

1;
