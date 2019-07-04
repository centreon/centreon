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

package modules::gorgonenewtest::class;

use strict;
use warnings;
use centreon::gorgone::common;
use centreon::misc::objects::object;
use ZMQ::LibZMQ4;
use ZMQ::Constants qw(:all);
use MIME::Base64;
use JSON::XS;
use Data::Dumper;
use modules::gorgonenewtest::newtest::stubs::ManagementConsoleService;
use modules::gorgonenewtest::newtest::stubs::errors;
use Date::Parse;

my %handlers = (TERM => {}, HUP => {});
my ($connector, $socket);

sub new {
    my ($class, %options) = @_;
    $connector  = {};
    $connector->{logger} = $options{logger};
    $connector->{container_id} = $options{container_id};
    $connector->{config} = $options{config};
    $connector->{config_core} = $options{config_core};
    $connector->{config_newtest} = $options{config_newtest};
    $connector->{config_db_centstorage} = $options{config_db_centstorage};
    $connector->{config_db_centreon} = $options{config_db_centreon};
    $connector->{stop} = 0;

    $connector->{resync_time} = $options{config_newtest}->{resync_time};
    $connector->{last_resync_time} = time() - $connector->{resync_time};

    $connector->{endpoint} = $options{config_newtest}->{nmc_endpoint};
    $connector->{nmc_username} = $options{config_newtest}->{nmc_username};
    $connector->{nmc_password} = $options{config_newtest}->{nmc_password};
    $connector->{nmc_timeout} = $options{config_newtest}->{nmc_timeout};
    $connector->{poller_name} = $options{config_newtest}->{poller_name};
    $connector->{list_scenario_status} = $options{config_newtest}->{list_scenario_status};
    $connector->{host_template} = $options{config_newtest}->{host_template};
    $connector->{host_prefix} = $options{config_newtest}->{host_prefix};
    $connector->{service_template} = $options{config_newtest}->{service_template};
    $connector->{service_prefix} = $options{config_newtest}->{service_prefix};

    $connector->{clapi_generate_config_timeout} = defined($options{config}->{clapi_generate_config_timeout}) ? $options{config}->{clapi_generate_config_timeout} : 180;
    $connector->{clapi_timeout} = defined($options{config}->{clapi_timeout}) ? $options{config}->{clapi_timeout} : 10;
    $connector->{clapi_command} = defined($options{config}->{clapi_command}) && $options{config}->{clapi_command} ne '' ? $options{config}->{clapi_command} : '/usr/bin/centreon';
    $connector->{clapi_username} = $options{config}->{clapi_username};
    $connector->{clapi_password} = $options{config}->{clapi_password};
    $connector->{clapi_action_applycfg} = $options{config}->{clapi_action_applycfg};
    $connector->{cmdFile} = defined($options{config}->{centcore_cmd}) && $options{config}->{centcore_cmd} ne '' ? $options{config}->{centcore_cmd} : '/var/lib/centreon/centcore.cmd';

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
    $self->{logger}->writeLogInfo("gorgone-newtest $$ Receiving order to stop...");
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

my %map_scenario_status = (
    Available => 0, Warning => 1, Failed => 2, Suspended => 2,
    Canceled => 2, Unknown => 3, OutOfRange => 3,
);

my %map_newtest_units = (
    Second => 's', Millisecond => 'ms', BytePerSecond => 'Bps', UnitLess => '', Unknown => '',
);

my %map_service_status = (
    0 => 'OK', 1 => 'WARNING', 2 => 'CRITICAL', 3 => 'UNKNOWN', 4 => 'PENDING',
);

sub newtestresync_init {
    my ($self, %options) = @_;

    # list from robot/scenario from db
    #   Format = { robot_name1 => { scenario1 => { last_execution_time => xxxx }, scenario2 => { } }, ... }
    $self->{db_newtest} = {};
    $self->{api_newtest} = {};
    $self->{poller_id} = undef;
    $self->{must_push_config} = 0;
    $self->{external_commands} = [];
    $self->{perfdatas} = [];
    $self->{cache_robot_list_results} = undef;
}


sub perfdata_add {
    my ($self, %options) = @_;
   
    my $perfdata = {label => '', value => '', unit => '', warning => '', critical => '', min => '', max => ''}; 
    foreach (keys %options) {
        next if (!defined($options{$_}));
        $perfdata->{$_} = $options{$_};
    }
    $perfdata->{label} =~ s/'/''/g;
    push @{$self->{perfdatas}}, $perfdata;
}

sub add_output {
    my ($self, %options) = @_;
    
    my $str = $map_service_status{$self->{current_status}} . ': ' . $self->{current_text} . '|';
    foreach my $perf (@{$self->{perfdatas}}) {
        $str .= " '" . $perf->{label} . "'=" . $perf->{value} . $perf->{unit} . ";" . $perf->{warning} . ";" . $perf->{critical} . ";" . $perf->{min} . ";" . $perf->{max};
    }
    $self->{perfdatas} = [];
    
    $self->push_external_cmd(cmd => 'PROCESS_SERVICE_CHECK_RESULT;' . $options{host_name} . ';' . 
                                        $options{service_name} . ';' . $self->{current_status} . ';' . $str,
                             time => $options{time});
}

sub convert_measure {
    my ($self, %options) = @_;
    
    if (defined($map_newtest_units{$options{unit}}) && 
        $map_newtest_units{$options{unit}} eq 'ms') {
        $options{value} /= 1000;
        $options{unit} = 's';
    }
    return ($options{value}, $options{unit});
}

sub get_poller_id {
    my ($self, %options) = @_;

    my ($status, $datas) = $self->{class_object_centreon}->custom_execute(
        request => 'SELECT id FROM nagios_server WHERE name = ' . $self->{class_object_centreon}->quote(value => $self->{poller_name}),
        mode => 2
    );
    if ($status == -1) {
        $self->{logger}->writeLogError("gorgone-newtest cannot get poller id for poller '" . $self->{poller_name} . "'.");
        return 1;
    }
    
    if (!defined($datas->[0])) {
        $self->{logger}->writeLogError("gorgone-newtest cannot find poller id for poller '" . $self->{poller_name} . "'.");
        return 1;
    }

    $self->{poller_id} = $datas->[0]->[0];
    return 0;
}

sub get_centreondb_cache {
    my ($self, %options) = @_;
    
    my $request = '
        SELECT host.host_name, service.service_description 
        FROM host 
        LEFT JOIN (host_service_relation, service) ON 
            (host_service_relation.host_host_id = host.host_id AND 
             service.service_id = host_service_relation.service_service_id AND 
             service.service_description LIKE ' . $self->{class_object_centreon}->quote(value => $self->{service_prefix}) . ') 
        WHERE host_name LIKE ' . $self->{class_object_centreon}->quote(value => $self->{host_prefix}) . " AND host_register = '1'";
    $request =~ s/%s/%/g;
    my ($status, $datas) = $self->{class_object_centreon}->custom_execute(
        request => $request, 
        mode => 2
    );    
    if ($status == -1) {
        $self->{logger}->writeLogError("gorgone-newtest cannot get robot/scenarios list from centreon db.");
        return 1;
    }
    
    foreach (@$datas) {
        $self->{db_newtest}->{$_->[0]} = {} if (!defined($self->{db_newtest}->{$_->[0]}));
        if (defined($_->[1])) {
            $self->{db_newtest}->{$_->[0]}->{$_->[1]} = {};
        }
    }

    return 0;
}

sub get_centstoragedb_cache {
    my ($self, %options) = @_;
    
    my $request = 'SELECT hosts.name, services.description, services.last_check 
                        FROM hosts LEFT JOIN services ON (services.host_id = hosts.host_id AND services.description LIKE ' . $self->{class_object_centstorage}->quote(value => $self->{service_prefix}) . ')  
                        WHERE name like ' . $self->{class_object_centstorage}->quote(value => $self->{host_prefix});
    $request =~ s/%s/%/g;
    my ($status, $datas) = $self->{class_object_centstorage}->custom_execute(
        request => $request, 
        mode => 2
    );    
    if ($status == -1) {
        $self->{logger}->writeLogError("gorgone-newtest cannot get robot/scenarios list from centstorage db.");
        return 1;
    }
    
    foreach (@$datas) {
        if (!defined($self->{db_newtest}->{$_->[0]})) {
            $self->{logger}->writeLogError("gorgone-newtest host '" . $_->[0]  . "'is in censtorage DB but not in centreon config...");
            next;
        }
        if (defined($_->[1]) && !defined($self->{db_newtest}->{$_->[0]}->{$_->[1]})) {
            $self->{logger}->writeLogError("gorgone-newtest host scenario '" . $_->[0]  . "/" .  $_->[1] . "' is in censtorage DB but not in centreon config...");
            next;
        }
        
        if (defined($_->[1])) {
            $self->{db_newtest}->{$_->[0]}->{$_->[1]}->{last_execution_time} = $_->[2];
        }
    }

    return 0;
}

sub clapi_execute {
    my ($self, %options) = @_;

    my $cmd = $self->{clapi_command} . " -u '" . $self->{clapi_username} . "' -p '" . $self->{clapi_password} . "' " . $options{cmd};
    my ($lerror, $stdout, $exit_code) = centreon::misc::misc::backtick(
        command => $cmd,
        logger => $self->{logger},
        timeout => $options{timeout},
        wait_exit => 1,
    );
    if ($lerror == -1 || ($exit_code >> 8) != 0) {
        $self->{logger}->writeLogError("gorgone-newtest clapi execution problem for command $cmd : " . $stdout);
        return -1;
    }

    return 0;
}

sub push_external_cmd {
    my ($self, %options) = @_;
    my $time = defined($options{time}) ? $options{time} : time();

    push @{$self->{external_commands}}, 
        'EXTERNALCMD:' . $self->{poller_id} . ':[' . $time . '] ' . $options{cmd};
}

sub submit_external_cmd {
    my ($self, %options) = @_;
    
    foreach my $cmd (@{$self->{external_commands}}) {
        my ($lerror, $stdout, $exit_code) = centreon::misc::misc::backtick(command => '/bin/echo "' . $cmd . '" >> ' . $self->{cmdFile},
            logger => $self->{logger},
            timeout => 5,
            wait_exit => 1
        );
        if ($lerror == -1 || ($exit_code >> 8) != 0) {
            $self->{logger}->writeLogError("gorgone-newtest clapi execution problem for command $cmd : " . $stdout);
            return -1;
        }
    }
}

sub push_config {
    my ($self, %options) = @_;

    if ($self->{must_push_config} == 1) {
        $self->{logger}->writeLogInfo("gorgone-newtest generation config for '$self->{poller_name}':");
        if ($self->clapi_execute(cmd => '-a POLLERGENERATE -v ' . $self->{poller_id},
                                 timeout => $self->{clapi_generate_config_timeout}) != 0) {
            $self->{logger}->writeLogError("gorgone-newtest generation config for '$self->{poller_name}': failed");
            return ;
        }
        $self->{logger}->writeLogError("gorgone-newtest generation config for '$self->{poller_name}': succeeded.");
        
        $self->{logger}->writeLogInfo("gorgone-newtest move config for '$self->{poller_name}':");
        if ($self->clapi_execute(cmd => '-a CFGMOVE -v ' . $self->{poller_id},
                                timeout => $self->{clapi_timeout}) != 0) {
            $self->{logger}->writeLogError("gorgone-newtest move config for '$self->{poller_name}': failed");
            return ;
        }
        $self->{logger}->writeLogError("gorgone-newtest move config for '$self->{poller_name}': succeeded.");
        
        $self->{logger}->writeLogInfo("gorgone-newtest restart/reload config for '$self->{poller_name}':");
        if ($self->clapi_execute(cmd => '-a ' . $self->{clapi_action_applycfg} . ' -v ' . $self->{poller_id},
                                timeout => $self->{clapi_timeout}) != 0) {
            $self->{logger}->writeLogError("gorgone-newtest restart/reload config for '$self->{poller_name}': failed");
            return ;
        }
        $self->{logger}->writeLogError("gorgone-newtest restart/reload config for '$self->{poller_name}': succeeded.");
    }
}

sub get_newtest_diagnostic {
    my ($self, %options) = @_;
    
    my $result = $self->{instance}->ListMessages('Instance', 30, 'Diagnostics', [$options{scenario}, $options{robot}]);
    if (defined(my $com_error = centreon::newtest::stubs::errors::get_error())) {
        $self->{logger}->writeLogError("gorgone-newtest newtest API error 'ListMessages' method: " . $com_error);
        return -1;
    }
    
    if (!(ref($result) && defined($result->{MessageItem}))) {
        $self->{logger}->writeLogError("gorgone-newtest no diagnostic found for scenario: " . $options{scenario} . '/' . $options{robot});
        return 1;
    }
    if (ref($result->{MessageItem}) eq 'HASH') {
            $result->{MessageItem} = [$result->{MessageItem}];
    }
    
    my $macro_value = '';
    my $macro_append = ''; 
    foreach my $item (@{$result->{MessageItem}}) {
        if (defined($item->{SubCategory})) {
            $macro_value .= $macro_append . $item->{SubCategory} . ':' . $item->{Id};
            $macro_append = '|';
        }
    }
    
    if ($macro_value ne '') {
        $self->push_external_cmd(cmd => 
            'CHANGE_CUSTOM_SVC_VAR;' . $options{host_name} . ';' . 
             $options{service_name} . ';NEWTEST_MESSAGEID;' . $macro_value
        );
    }
    return 0;
}

sub get_scenario_results {
    my ($self, %options) = @_;
    
    # Already test the robot but no response
    if (defined($self->{cache_robot_list_results}->{$options{robot}}) && 
        !defined($self->{cache_robot_list_results}->{$options{robot}}->{ResultItem})) {
        $self->{current_text} = sprintf("gorgone-newtest no result avaiblable for scenario '%s'", $options{scenario});
        $self->{current_status} = 3;
        return 1;
    }
    if (!defined($self->{cache_robot_list_results}->{$options{robot}})) {
        my $result = $self->{instance}->ListResults('Robot', 30, [$options{robot}]);
        if (defined(my $com_error = centreon::newtest::stubs::errors::get_error())) {
            $self->{logger}->writeLogError("gorgone-newtest newtest API error 'ListResults' method: " . $com_error);
            return -1;
        }
        
        if (!(ref($result) && defined($result->{ResultItem}))) {
            $self->{cache_robot_list_results}->{$options{robot}} = {};
            $self->{logger}->writeLogError("gorgone-newtest no results found for robot: " . $options{robot});
            return 1;
        }
        
        if (ref($result->{ResultItem}) eq 'HASH') {
            $result->{ResultItem} = [$result->{ResultItem}];
        }
        $self->{cache_robot_list_results}->{$options{robot}} = $result;
    }
    
    # stop at first
    foreach my $result (@{$self->{cache_robot_list_results}->{$options{robot}}->{ResultItem}}) {
        if ($result->{MeasureName} eq $options{scenario}) {
            my ($value, $unit) = $self->convert_measure(
                value => $result->{ExecutionValue},
                unit => $result->{MeasureUnit}
            );
            $self->{current_text} = sprintf(
                "Execution status '%s'. Scenario '%s' total duration is %d%s.",
                 $result->{ExecutionStatus}, $options{scenario}, 
                 $value, $unit
            );
            $self->perfdata_add(
                label => $result->{MeasureName}, unit => $unit, 
                value => sprintf("%d", $value), 
                min => 0
            );
            
            $self->get_newtest_extra_metrics(
                scenario => $options{scenario},
                robot => $options{robot},
                id => $result->{Id}
            );
            return 0;
        }
    }
    
    $self->{logger}->writeLogError("gorgone-newtest  no result found for scenario: " . $options{scenario} . '/' . $options{robot});
    return 1;
}

sub get_newtest_extra_metrics {
    my ($self, %options) = @_;
    
    my $result = $self->{instance}->ListResultChildren($options{id});
    if (defined(my $com_error = centreon::newtest::stubs::errors::get_error())) {
        $self->{logger}->writeLogError("gorgone-newtest newtest API error 'ListResultChildren' method: " . $com_error);
        return -1;
    }
    
    if (!(ref($result) && defined($result->{ResultItem}))) {
        $self->{logger}->writeLogError("gorgone-newtest no extra metrics found for scenario: " . $options{scenario} . '/' . $options{robot});
        return 1;
    }
    
    if (ref($result->{ResultItem}) eq 'HASH') {
        $result->{ResultItem} = [$result->{ResultItem}];
    }
    foreach my $item (@{$result->{ResultItem}}) {
        $self->perfdata_add(
            label => $item->{MeasureName}, unit => $map_newtest_units{$item->{MeasureUnit}}, 
            value => $item->{ExecutionValue}
        );
    }
    return 0;
}

sub get_newtest_scenarios {
    my ($self, %options) = @_;

    eval {
        $self->{instance}->proxy($self->{endpoint}, timeout => $self->{nmc_timeout});
    };
    if ($@) {
        $self->{logger}->writeLogError('gorgone-newtest newtest proxy error: ' . $@);
        return -1;
    }

    if (defined($self->{nmc_username}) && $self->{nmc_username} ne '' &&
        defined($self->{nmc_password}) && $self->{nmc_password} ne '') {
        $self->{instance}->transport->http_request->header(
            'Authorization' => 'Basic ' . MIME::Base64::encode($self->{nmc_username} . ':' . $self->{nmc_password}, '')
        );
    }
    my $result = $self->{instance}->ListScenarioStatus(
        $self->{list_scenario_status}->{search}, 
        0, 
        $self->{list_scenario_status}->{instances}
    );
    if (defined(my $com_error = modules::gorgonenewtest::newtest::stubs::errors::get_error())) {
        $self->{logger}->writeLogError("gorgone-newtest newtest API error 'ListScenarioStatus' method: " . $com_error);
        return -1;
    }
    
    if (defined($result->{InstanceScenarioItem})) {
        if (ref($result->{InstanceScenarioItem}) eq 'HASH') {
            $result->{InstanceScenarioItem} = [$result->{InstanceScenarioItem}];
        }

        foreach my $scenario (@{$result->{InstanceScenarioItem}}) {
            my $scenario_name = $scenario->{MeasureName};
            my $robot_name = $scenario->{RobotName};
            my $last_check = sprintf("%d", Date::Parse::str2time($scenario->{LastMessageUtc}, 'UTC'));
            my $host_name = sprintf($self->{host_prefix}, $robot_name);
            my $service_name = sprintf($self->{service_prefix}, $scenario_name);
            $self->{current_status} = $map_scenario_status{$scenario->{Status}};
            $self->{current_text} = '';
            
            # Add host config
            if (!defined($self->{db_newtest}->{$host_name})) {
                $self->{logger}->writeLogInfo("gorgone-newtest create host '$host_name'");
                if ($self->clapi_execute(cmd => '-o HOST -a ADD -v "' . $host_name . ';' . $host_name . ';127.0.0.1;' . $self->{host_template} . ';' . $self->{poller_name} . ';"',
                                         timeout => $self->{clapi_timeout}) == 0) {
                    $self->{db_newtest}->{$host_name} = {};
                    $self->{must_push_config} = 1;
                    $self->{logger}->writeLogInfo("gorgone-newtest create host '$host_name' succeeded.");
                }
            }
            
            # Add service config
            if (defined($self->{db_newtest}->{$host_name}) && !defined($self->{db_newtest}->{$host_name}->{$service_name})) {
                $self->{logger}->writeLogInfo("gorgone-newtest create service '$service_name' for host '$host_name':");
                if ($self->clapi_execute(cmd => '-o SERVICE -a ADD -v "' . $host_name . ';' . $service_name . ';' . $self->{service_template} . '"',
                                         timeout => $self->{clapi_timeout}) == 0) {
                    $self->{db_newtest}->{$host_name}->{$service_name} = {};
                    $self->{must_push_config} = 1;
                    $self->{logger}->writeLogInfo("gorgone-newtest create service '$service_name' for host '$host_name' succeeded.");
                    $self->clapi_execute(cmd => '-o SERVICE -a setmacro -v "' . $host_name . ';' . $service_name . ';NEWTEST_MESSAGEID;"',
                                         timeout => $self->{clapi_timeout});
                }
            }
            
            # Check if new message
            if (defined($self->{db_newtest}->{$host_name}->{$service_name}->{last_execution_time}) &&
                $last_check <= $self->{db_newtest}->{$host_name}->{$service_name}->{last_execution_time}) {
                $self->{logger}->writeLogInfo("gorgone-newtest skip: service '$service_name' for host '$host_name' already submitted.");
                next;
            }
            
            if ($self->{current_status} == 2) {
                $self->get_newtest_diagnostic(
                    scenario => $scenario_name, robot => $robot_name,
                    host_name => $host_name, service_name => $service_name
                );
            }
            
            if ($self->get_scenario_results(scenario => $scenario_name, robot => $robot_name,
                                            host_name => $host_name, service_name => $service_name) == 1) {
                $self->{current_text} = sprintf("No result avaiblable for scenario '%s'", $scenario_name);
                $self->{current_status} = 3;
            }
            $self->add_output(time => $last_check, host_name => $host_name, service_name => $service_name);
        }
    }

    return 0;
}

sub action_newtestresync {
    my ($self, %options) = @_;

    $self->{logger}->writeLogDebug("gorgone-newtest: container $self->{container_id}: begin resync");
    $self->newtestresync_init();
    
    return -1 if ($self->get_poller_id());
    return -1 if ($self->get_centreondb_cache());
    return -1 if ($self->get_centstoragedb_cache());
    
    return -1 if ($self->get_newtest_scenarios(%options));   

    $self->push_config();
    $self->submit_external_cmd();

    return 0;
}

sub event {
    while (1) {
        my $message = centreon::gorgone::common::zmq_dealer_read_message(socket => $socket);
        
        $connector->{logger}->writeLogDebug("gorgone-newtest: class: $message");
        if ($message =~ /^\[(.*?)\]/) {
            if ((my $method = $connector->can('action_' . lc($1)))) {
                $message =~ /^\[(.*?)\]\s+\[(.*?)\]\s+\[.*?\]\s+(.*)$/m;
                my ($action, $token) = ($1, $2);
                my $data = JSON::XS->new->utf8->decode($3);
                while ($method->($connector, token => $token, data => $data)) {
                    # We block until it's fixed!!
                    sleep(5);
                }
            }
        }

        last unless (centreon::gorgone::common::zmq_still_read(socket => $socket));
    }
}

sub run {
    my ($self, %options) = @_;

    # Database creation. We stay in the loop still there is an error
    $self->{db_centstorage} = centreon::misc::db->new(
        dsn => $self->{config_db_centstorage}{dsn},
        user => $self->{config_db_centstorage}{username},
        password => $self->{config_db_centstorage}{password},
        force => 2,
        logger => $self->{logger}
    );
    $self->{db_centreon} = centreon::misc::db->new(
        dsn => $self->{config_db_centreon}{dsn},
        user => $self->{config_db_centreon}{username},
        password => $self->{config_db_centreon}{password},
        force => 2,
        logger => $self->{logger}
    );
    ##### Load objects #####
    $self->{class_object_centstorage} = centreon::misc::objects::object->new(logger => $self->{logger}, db_centreon => $self->{db_centstorage});
    $self->{class_object_centreon} = centreon::misc::objects::object->new(logger => $self->{logger}, db_centreon => $self->{db_centreon});
    $SOAP::Constants::PREFIX_ENV = 'SOAP-ENV';
    $self->{instance} = modules::gorgonenewtest::newtest::stubs::ManagementConsoleService->new();

    # Connect internal
    $socket = centreon::gorgone::common::connect_com(
        zmq_type => 'ZMQ_DEALER', name => 'gorgonenewtest-' . $self->{container_id},
        logger => $self->{logger},
        type => $self->{config_core}{internal_com_type},
        path => $self->{config_core}{internal_com_path}
    );
    centreon::gorgone::common::zmq_send_message(
        socket => $socket,
        action => 'NEWTESTREADY', data => { container_id => $self->{container_id} },
        json_encode => 1
    );
    $self->{poll} = [
        {
            socket  => $socket,
            events  => ZMQ_POLLIN,
            callback => \&event,
        }
    ];
    while (1) {
        # we try to do all we can
        my $rev = zmq_poll($self->{poll}, 5000);
        if (defined($rev) && $rev == 0 && $self->{stop} == 1) {
            $self->{logger}->writeLogInfo("gorgone-newtest $$ has quit");
            zmq_close($socket);
            exit(0);
        }

        if (time() - $self->{resync_time} > $self->{last_resync_time}) {
            $self->{last_resync_time} = time();
            $self->action_newtestresync();
        }
    }
}

1;
