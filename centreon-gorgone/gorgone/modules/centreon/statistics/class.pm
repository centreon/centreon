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

package gorgone::modules::centreon::statistics::class;

use base qw(gorgone::class::module);

use strict;
use warnings;
use gorgone::standard::library;
use gorgone::class::sqlquery;
use ZMQ::LibZMQ4;
use ZMQ::Constants qw(:all);
use File::Path qw(make_path);
use JSON::XS;
use Time::HiRes;
use RRDs;

my $result;
my %handlers = (TERM => {}, HUP => {});
my ($connector);

sub new {
    my ($class, %options) = @_;

    $connector  = {};
    $connector->{internal_socket} = undef;
    $connector->{module_id} = $options{module_id};
    $connector->{logger} = $options{logger};
    $connector->{config} = $options{config};
    $connector->{config_core} = $options{config_core};
    $connector->{config_db_centreon} = $options{config_db_centreon};
    $connector->{config_db_centstorage} = $options{config_db_centstorage};
    $connector->{stop} = 0;

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
    $self->{logger}->writeLogInfo("[statistics] $$ Receiving order to stop...");
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

sub get_pollers_config {
    my ($self, %options) = @_;

    my ($status, $data) = $self->{class_object_centreon}->custom_execute(
        request => "SELECT id, nagiostats_bin, cfg_dir, cfg_file FROM cfg_nagios " .
            "JOIN nagios_server " .
            "WHERE ns_activate = '1' AND nagios_id = id",
        mode => 1,
        keys => 'id'
    );
    if ($status == -1) {
        $self->{logger}->writeLogError('[engine] Cannot get Pollers configuration');
        return -1;
    }
    
    return $data;
}

sub get_broker_stats_collection_flag {
    my ($self, %options) = @_;

    my ($status, $data) = $self->{class_object_centreon}->custom_execute(
        request => "SELECT `value` FROM options WHERE `key` = 'enable_broker_stats'",
        mode => 2
    );
    if ($status == -1 || !defined($data->[0][0])) {
        $self->{logger}->writeLogError('[statistics] Cannot get Broker statistics collection flag');
        return -1;
    }
    
    return $data->[0][0];
}

sub action_brokerstats {
    my ($self, %options) = @_;

    $options{token} = $self->generate_token() if (!defined($options{token}));

    $self->send_log(
        code => gorgone::class::module::ACTION_BEGIN,
        token => $options{token},
        data => {
            message => 'action brokerstats starting'
        }
    );

    if ($self->get_broker_stats_collection_flag() < 1) {
        $self->send_log(
            code => gorgone::class::module::ACTION_FINISH_OK,
            token => $options{token},
            data => {
                message => 'no collection configured'
            }
        );
        $self->{logger}->writeLogDebug("[statistics] No Broker statistics collection configured");
        return 0;
    }

    my $request = "SELECT id, cache_directory, config_name FROM cfg_centreonbroker " .
        "JOIN nagios_server " .
        "WHERE ns_activate = '1' AND stats_activate = '1' AND ns_nagios_server = id";

    if (defined($options{data}->{variables}[0]) && $options{data}->{variables}[0] =~ /\d+/) {
        $request .= " AND id = '" . $options{data}->{variables}[0] . "'";
    }
    
    if (!defined($options{data}->{content}->{collect_localhost}) ||
        $options{data}->{content}->{collect_localhost} eq 'false') {
        $request .= " AND localhost = '0'";
    }

    my ($status, $data) = $self->{class_object_centreon}->custom_execute(request => $request, mode => 2);
    if ($status == -1) {
        $self->send_log(
            code => gorgone::class::module::ACTION_FINISH_KO,
            token => $options{token},
            data => {
                message => 'cannot find configuration'
            }
        );
        $self->{logger}->writeLogError("[statistics] Cannot find configuration");
        return 1;
    }

    my %targets;
    foreach (@{$data}) {
        my $target = $_->[0];
        my $statistics_file = $_->[1] . "/" . $_->[2] . "-stats.json";
        $self->{logger}->writeLogInfo(
            "[statistics] Collecting Broker statistics file '" . $statistics_file . "' from target '" . $target . "'"
        );
        
        $self->send_internal_action(
            target => $target,
            action => 'COMMAND',
            token => $options{token},
            data => {
                content => [ 
                    {
                        instant => 1,
                        command => 'cat ' . $statistics_file,
                        timeout => $options{data}->{content}->{timeout},
                        metadata => {
                            poller_id => $target,
                            config_name => $_->[2],
                            source => 'brokerstats'
                        }
                    }
                ]
            }
        );
        $targets{$target} = 1;
    }
    
    my $wait = (defined($self->{config}->{command_wait})) ? $self->{config}->{command_wait} : 1_000_000;
    Time::HiRes::usleep($wait);
    
    foreach my $target (keys %targets) {
        $self->send_internal_action(
            target => $target,
            action => 'GETLOG',
        );
    }

    $wait = (defined($self->{config}->{sync_wait})) ? $self->{config}->{sync_wait} : 1_000_000;
    Time::HiRes::usleep($wait);

    $self->send_log(
        code => $self->ACTION_FINISH_OK,
        token => $options{token},
        data => {
            message => 'action brokerstats finished'
        }
    );
    
    $self->send_internal_action(
        action => 'GETLOG',
        data => {
            token => $options{token}
        }
    );
    return 0;
}

sub action_enginestats {
    my ($self, %options) = @_;

    $options{token} = $self->generate_token() if (!defined($options{token}));

    $self->send_log(
        code => gorgone::class::module::ACTION_BEGIN,
        token => $options{token},
        data => {
            message => 'action enginestats starting'
        }
    );

    my $pollers = $self->get_pollers_config();

    foreach (keys %{$pollers}) {
        my $target = $_;
        my $enginestats_file = $pollers->{$_}->{nagiostats_bin};
        my $config_file = $pollers->{$_}->{cfg_dir} . '/' . $pollers->{$_}->{cfg_file};
        $self->{logger}->writeLogInfo(
            "[statistics] Collecting Engine statistics from target '" . $target . "'"
        );
        $self->send_internal_action(
            target => $target,
            action => 'COMMAND',
            token => $options{token},
            data => {
                content => [ 
                    {
                        instant => 1,
                        command => $enginestats_file . ' -c ' . $config_file,
                        timeout => $options{data}->{content}->{timeout},
                        metadata => {
                            poller_id => $target,
                            source => 'enginestats'
                        }
                    }
                ]
            }
        );
    }
    
    my $wait = (defined($self->{config}->{command_wait})) ? $self->{config}->{command_wait} : 2_000_000;
    Time::HiRes::usleep($wait);
    
    foreach my $target (keys %{$pollers}) {
        $self->send_internal_action(
            target => $target,
            action => 'GETLOG',
        );
    }

    $wait = (defined($self->{config}->{sync_wait})) ? $self->{config}->{sync_wait} : 2_000_000;
    Time::HiRes::usleep($wait);

    $self->send_log(
        code => $self->ACTION_FINISH_OK,
        token => $options{token},
        data => {
            message => 'action enginestats finished'
        }
    );
    
    $self->send_internal_action(
        action => 'GETLOG',
        data => {
            token => $options{token}
        }
    );
    return 0;
}

sub write_broker_stats {
    my ($self, %options) = @_;

    return if (!defined($options{data}->{result}->{exit_code}) || $options{data}->{result}->{exit_code} != 0 ||
        !defined($options{data}->{metadata}->{poller_id}) || !defined($options{data}->{metadata}->{config_name}));

    my $broker_cache_dir = $self->{config}->{broker_cache_dir} . '/' . $options{data}->{metadata}->{poller_id};
    
    if (! -d $broker_cache_dir ) {
        if (make_path($broker_cache_dir) == 0) {
            $self->{logger}->writeLogError("[statistics] Cannot create directory '" . $broker_cache_dir . "': $!");
            return 1;
        }
    }

    my $dest_file = $broker_cache_dir . '/' . $options{data}->{metadata}->{config_name} . '.json';
    $self->{logger}->writeLogDebug("[statistics] Writing file '" . $dest_file . "'");
    open(FH, '>', $dest_file);
    print FH $options{data}->{result}->{stdout};
    close(FH);

    return 0
}


sub write_engine_stats {
    my ($self, %options) = @_;

    return if (!defined($options{data}->{result}->{exit_code}) || $options{data}->{result}->{exit_code} != 0 ||
        !defined($options{data}->{metadata}->{poller_id}));

    my $engine_stats_dir = $self->{config}->{engine_stats_dir} . '/perfmon-' . $options{data}->{metadata}->{poller_id};

    if (! -d $engine_stats_dir ) {
        if (make_path($engine_stats_dir) == 0) {
            $self->{logger}->writeLogError("[statistics] Cannot create directory '" . $engine_stats_dir . "': $!");
            return 1;
        }
    }

    foreach (split(/\n/, $options{data}->{result}->{stdout})) {
        if ($_ =~ /Used\/High\/Total Command Buffers:\s*([0-9\.]*)\ \/\ ([0-9\.]*)\ \/\ ([0-9\.]*)/) {
            my $dest_file = $engine_stats_dir . '/nagios_cmd_buffer.rrd';
            $self->{logger}->writeLogDebug("[statistics] Writing in file '" . $dest_file . "'");
            if (!-e $dest_file) {
                next if ($self->rrd_create(
                    file => $dest_file,
                    heartbeat => $self->{config}->{heartbeat},
                    interval => $self->{config}->{interval},
                    number => $self->{config}->{number},
                    ds => [ "In_Use", "Max_Used", "Total_Available" ]
                ));
            }
            $self->rrd_update(
                file => $dest_file,
                ds => [ "In_Use", "Max_Used", "Total_Available" ],
                values => [ $1, $2 , $3 ]
            );
        } elsif ($_ =~ /Active Service Latency:\s*([0-9\.]*)\ \/\ ([0-9\.]*)\ \/\ ([0-9\.]*)\ sec/) {
            my $status = $self->{class_object_centstorage}->custom_execute(
                request => "DELETE FROM `nagios_stats` WHERE instance_id = '" . $options{data}->{metadata}->{poller_id} . "'"
            );
            if ($status == -1) {
                $self->{logger}->writeLogError("[statistics] Failed to delete statistics in 'nagios_stats table'");
            } else {
                my $status = $self->{class_object_centstorage}->custom_execute(
                    request => "INSERT INTO `nagios_stats` (instance_id, stat_label, stat_key, stat_value) VALUES " .
                        "('$options{data}->{metadata}->{poller_id}', 'Service Check Latency', 'Min', '$1'), " .
                        "('$options{data}->{metadata}->{poller_id}', 'Service Check Latency', 'Max', '$2'), " .
                        "('$options{data}->{metadata}->{poller_id}', 'Service Check Latency', 'Average', '$3')"
                );
                if ($status == -1) {
                    $self->{logger}->writeLogError("[statistics] Failed to add statistics in 'nagios_stats table'");
                }
            }

            my $dest_file = $engine_stats_dir . '/nagios_active_service_latency.rrd';
            $self->{logger}->writeLogDebug("[statistics] Writing in file '" . $dest_file . "'");
            if (!-e $dest_file) {
                next if ($self->rrd_create(
                    file => $dest_file,
                    heartbeat => $self->{config}->{heartbeat},
                    interval => $self->{config}->{interval},
                    number => $self->{config}->{number},
                    ds => [ "Min", "Max", "Average" ]
                ));
            }
            $self->rrd_update(
                file => $dest_file,
                ds => [ "Min", "Max", "Average" ],
                values => [ $1, $2 , $3 ]
            );
        } elsif ($_ =~ /Active Service Execution Time:\s*([0-9\.]*)\ \/\ ([0-9\.]*)\ \/\ ([0-9\.]*)\ sec/) {
            my $dest_file = $engine_stats_dir . '/nagios_active_service_execution.rrd';
            $self->{logger}->writeLogDebug("[statistics] Writing in file '" . $dest_file . "'");
            if (!-e $dest_file) {
                next if ($self->rrd_create(
                    file => $dest_file,
                    heartbeat => $self->{config}->{heartbeat},
                    interval => $self->{config}->{interval},
                    number => $self->{config}->{number},
                    ds => [ "Min", "Max", "Average" ]
                ));
            }
            $self->rrd_update(
                file => $dest_file,
                ds => [ "Min", "Max", "Average" ],
                values => [ $1, $2 , $3 ]
            );
        } elsif ($_ =~ /Active Services Last 1\/5\/15\/60 min:\s*([0-9\.]*)\ \/\ ([0-9\.]*)\ \/\ ([0-9\.]*)\ \/\ ([0-9\.]*)/) {
            my $dest_file = $engine_stats_dir . '/nagios_active_service_last.rrd';
            $self->{logger}->writeLogDebug("[statistics] Writing in file '" . $dest_file . "'");
            if (!-e $dest_file) {
                next if ($self->rrd_create(
                    file => $dest_file,
                    heartbeat => $self->{config}->{heartbeat},
                    interval => $self->{config}->{interval},
                    number => $self->{config}->{number},
                    ds => [ "Last_Min", "Last_5_Min", "Last_15_Min", "Last_Hour" ]
                ));
            }
            $self->rrd_update(
                file => $dest_file,
                ds => [ "Last_Min", "Last_5_Min", "Last_15_Min", "Last_Hour" ],
                values => [ $1, $2 , $3, $4 ]
            );
        } elsif ($_ =~ /Services Ok\/Warn\/Unk\/Crit:\s*([0-9\.]*)\ \/\ ([0-9\.]*)\ \/\ ([0-9\.]*)\ \/\ ([0-9\.]*)/) {
            my $dest_file = $engine_stats_dir . '/nagios_services_states.rrd';
            $self->{logger}->writeLogDebug("[statistics] Writing in file '" . $dest_file . "'");
            if (!-e $dest_file) {
                next if ($self->rrd_create(
                    file => $dest_file,
                    heartbeat => $self->{config}->{heartbeat},
                    interval => $self->{config}->{interval},
                    number => $self->{config}->{number},
                    ds => [ "Ok", "Warn", "Unk", "Crit" ]
                ));
            }
            $self->rrd_update(
                file => $dest_file,
                ds => [ "Ok", "Warn", "Unk", "Crit" ],
                values => [ $1, $2 , $3, $4 ]
            );
        } elsif ($_ =~ /Active Host Latency:\s*([0-9\.]*)\ \/\ ([0-9\.]*)\ \/\ ([0-9\.]*)\ sec/) {
            my $dest_file = $engine_stats_dir . '/nagios_active_host_latency.rrd';
            $self->{logger}->writeLogDebug("[statistics] Writing in file '" . $dest_file . "'");
            if (!-e $dest_file) {
                next if ($self->rrd_create(
                    file => $dest_file,
                    heartbeat => $self->{config}->{heartbeat},
                    interval => $self->{config}->{interval},
                    number => $self->{config}->{number},
                    ds => [ "Min", "Max", "Average" ]
                ));
            }
            $self->rrd_update(
                file => $dest_file,
                ds => [ "Min", "Max", "Average" ],
                values => [ $1, $2 , $3 ]
            );
        } elsif ($_ =~ /Active Host Execution Time:\s*([0-9\.]*)\ \/\ ([0-9\.]*)\ \/\ ([0-9\.]*)\ sec/) {
            my $dest_file = $engine_stats_dir . '/nagios_active_host_execution.rrd';
            $self->{logger}->writeLogDebug("[statistics] Writing in file '" . $dest_file . "'");
            if (!-e $dest_file) {
                next if ($self->rrd_create(
                    file => $dest_file,
                    heartbeat => $self->{config}->{heartbeat},
                    interval => $self->{config}->{interval},
                    number => $self->{config}->{number},
                    ds => [ "Min", "Max", "Average" ]
                ));
            }
            $self->rrd_update(
                file => $dest_file,
                ds => [ "Min", "Max", "Average" ],
                values => [ $1, $2 , $3 ]
            );
        } elsif ($_ =~ /Active Hosts Last 1\/5\/15\/60 min:\s*([0-9\.]*)\ \/\ ([0-9\.]*)\ \/\ ([0-9\.]*)\ \/\ ([0-9\.]*)/) {
            my $dest_file = $engine_stats_dir . '/nagios_active_host_last.rrd';
            $self->{logger}->writeLogDebug("[statistics] Writing in file '" . $dest_file . "'");
            if (!-e $dest_file) {
                next if ($self->rrd_create(
                    file => $dest_file,
                    heartbeat => $self->{config}->{heartbeat},
                    interval => $self->{config}->{interval},
                    number => $self->{config}->{number},
                    ds => [ "Last_Min", "Last_5_Min", "Last_15_Min", "Last_Hour" ]
                ));
            }
            $self->rrd_update(
                file => $dest_file,
                ds => [ "Last_Min", "Last_5_Min", "Last_15_Min", "Last_Hour" ],
                values => [ $1, $2 , $3, $4 ]
            );
        } elsif ($_ =~ /Hosts Up\/Down\/Unreach:\s*([0-9\.]*)\ \/\ ([0-9\.]*)\ \/\ ([0-9\.]*)/) {
            my $dest_file = $engine_stats_dir . '/nagios_hosts_states.rrd';
            $self->{logger}->writeLogDebug("[statistics] Writing in file '" . $dest_file . "'");
            if (!-e $dest_file) {
                next if ($self->rrd_create(
                    file => $dest_file,
                    heartbeat => $self->{config}->{heartbeat},
                    interval => $self->{config}->{interval},
                    number => $self->{config}->{number},
                    ds => [ "Up", "Down", "Unreach" ]
                ));
            }
            $self->rrd_update(
                file => $dest_file,
                ds => [ "Up", "Down", "Unreach" ],
                values => [ $1, $2 , $3 ]
            );
        }
    }
}

sub rrd_create {
    my ($self, %options) = @_;

    my @ds;
    foreach my $ds (@{$options{ds}}) {
        push @ds, "DS:" . $ds . ":GAUGE:" . $options{interval} . ":0:U";
    }
    
    RRDs::create(
        $options{file},
        "-s" . $options{interval},
        @ds,
        "RRA:AVERAGE:0.5:1:" . $options{number},
        "RRA:AVERAGE:0.5:12:" . $options{number}
    );
    if (RRDs::error()) {
        my $error = RRDs::error();
        $self->{logger}->writeLogError("[statistics] Error creating RRD file '" . $options{file} . "': " . $error);
        return 1
    }
    
    foreach my $ds (@{$options{ds}}) {
        RRDs::tune($options{file}, "-h",  $ds . ":" . $options{heartbeat});
        if (RRDs::error()) {
            my $error = RRDs::error();
            $self->{logger}->writeLogError("[statistics] Error tuning RRD file '" . $options{file} . "': " . $error);
            return 1
        }
    }

    return 0;
}

sub rrd_update {
    my ($self, %options) = @_;

    my $append = '';
    my $ds;
    foreach (@{$options{ds}}) {
        $ds .= $append . $_;
        $append = ':';
    }
    my $values;
    foreach (@{$options{values}}) {
        $values .= $append . $_;
    }
    RRDs::update(
        $options{file},
        "--template",
        $ds,
        "N" . $values
    );
    if (RRDs::error()) {
        my $error = RRDs::error();
        $self->{logger}->writeLogError("[statistics] Error updating RRD file '" . $options{file} . "': " . $error);
        return 1
    }
    
    return 0;
}

sub write_stats {
    my ($self, %options) = @_;

    return if (!defined($options{data}->{data}->{action}) || $options{data}->{data}->{action} ne "getlog" &&
        defined($options{data}->{data}->{result}));

    foreach my $entry (@{$options{data}->{data}->{result}}) {
        my $data = JSON::XS->new->utf8->decode($entry->{data});
        
        if (defined($data->{metadata}->{source})) {
            if ($data->{metadata}->{source} eq "brokerstats") {
                $self->write_broker_stats(data => $data);
            } elsif ($data->{metadata}->{source} eq "enginestats") {
                $self->write_engine_stats(data => $data);
            }
        }
    }
}

sub event {
    while (1) {
        my $message = gorgone::standard::library::zmq_dealer_read_message(socket => $connector->{internal_socket});
        
        $connector->{logger}->writeLogDebug("[statistics] Event: $message");
        if ($message =~ /^\[ACK\]\s+\[(.*?)\]\s+(.*)$/m) {
            my $token = $1;
            my $data = JSON::XS->new->utf8->decode($2);
            my $method = $connector->can('write_stats');
            $method->($connector, data => $data);
        } else {
            $message =~ /^\[(.*?)\]\s+\[(.*?)\]\s+\[.*?\]\s+(.*)$/m;
            if ((my $method = $connector->can('action_' . lc($1)))) {
                $message =~ /^\[(.*?)\]\s+\[(.*?)\]\s+\[.*?\]\s+(.*)$/m;
                my ($action, $token) = ($1, $2);
                my $data = JSON::XS->new->utf8->decode($3);
                $method->($connector, token => $token, data => $data);
            }
        }

        last unless (gorgone::standard::library::zmq_still_read(socket => $connector->{internal_socket}));
    }
}

sub run {
    my ($self, %options) = @_;

    # Connect internal
    $connector->{internal_socket} = gorgone::standard::library::connect_com(
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgonestatistics',
        logger => $self->{logger},
        type => $self->{config_core}->{internal_com_type},
        path => $self->{config_core}->{internal_com_path}
    );
    $connector->send_internal_action(
        action => 'STATISTICSREADY',
        data => {}
    );

    $self->{db_centreon} = gorgone::class::db->new(
        dsn => $self->{config_db_centreon}->{dsn},
        user => $self->{config_db_centreon}->{username},
        password => $self->{config_db_centreon}->{password},
        force => 2,
        logger => $self->{logger}
    );
    $self->{class_object_centreon} = gorgone::class::sqlquery->new(
        logger => $self->{logger},
        db_centreon => $self->{db_centreon}
    );

    $self->{db_centstorage} = gorgone::class::db->new(
        dsn => $self->{config_db_centstorage}->{dsn},
        user => $self->{config_db_centstorage}->{username},
        password => $self->{config_db_centstorage}->{password},
        force => 2,
        logger => $self->{logger}
    );
    $self->{class_object_centstorage} = gorgone::class::sqlquery->new(
        logger => $self->{logger},
        db_centreon => $self->{db_centstorage}
    );

    $self->{poll} = [
        {
            socket  => $connector->{internal_socket},
            events  => ZMQ_POLLIN,
            callback => \&event,
        }
    ];

    if (defined($self->{config}->{cron})) {
        $self->send_internal_action(
            action => 'ADDCRON', 
            data => {
                content => $self->{config}->{cron},
            }
        );
    }

    while (1) {
        # we try to do all we can
        my $rev = zmq_poll($self->{poll}, 5000);
        if (defined($rev) && $rev == 0 && $self->{stop} == 1) {
            $self->{logger}->writeLogInfo("[statistics] $$ has quit");
            zmq_close($connector->{internal_socket});
            exit(0);
        }
    }
}

1;
