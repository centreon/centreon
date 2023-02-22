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

package gorgone::modules::centreon::audit::class;

use base qw(gorgone::class::module);

use strict;
use warnings;
use gorgone::standard::library;
use gorgone::standard::constants qw(:all);
use gorgone::standard::misc;
use gorgone::class::sqlquery;
use EV;

my %handlers = (TERM => {}, HUP => {});
my ($connector);

my @sampling_modules = (
    'system::cpu',
    'system::diskio'
);
my @metrics_modules = (
    'centreon::database',
    'centreon::packages',
    'centreon::pluginpacks',
    'centreon::realtime',
    'centreon::rrd',
    'system::cpu',
    'system::disk',
    'system::diskio',
    'system::load',
    'system::memory',
    'system::os'
);

sub new {
    my ($class, %options) = @_;
    $connector = $class->SUPER::new(%options);
    bless $connector, $class;

    $connector->{audit_tokens} = {};
    $connector->{sampling} = {};
    $connector->{sampling_modules} = {};
    $connector->{metrics_modules} = {};

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
    $self->{logger}->writeLogDebug("[audit] $$ Receiving order to stop...");
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

sub load_modules {
    my ($self, %options) = @_;

    foreach (@sampling_modules) {
        my $mod_name = 'gorgone::modules::centreon::audit::sampling::' . $_;
        my $ret = gorgone::standard::misc::mymodule_load(
            logger => $self->{logger},
            module => $mod_name,
            error_msg => "Cannot load sampling module '$_'"
        );
        next if ($ret == 1);
        $self->{sampling_modules}->{$_} = $mod_name->can('sample');
    }

    foreach (@metrics_modules) {
        my $mod_name = 'gorgone::modules::centreon::audit::metrics::' . $_;
        my $ret = gorgone::standard::misc::mymodule_load(
            logger => $self->{logger},
            module => $mod_name,
            error_msg => "Cannot load metrics module '$_'"
        );
        next if ($ret == 1);
        $self->{metrics_modules}->{$_} = $mod_name->can('metrics');
    }
}

sub action_centreonauditnode {
    my ($self, %options) = @_;

    $self->{logger}->writeLogDebug('[audit] action node starting');
    $options{token} = $self->generate_token() if (!defined($options{token}));

    $self->send_log(code => GORGONE_ACTION_BEGIN, token => $options{token}, data => { message => 'action node starting' });

    my $metrics = {};
    foreach my $name (keys %{$self->{metrics_modules}}) {
        my $result = $self->{metrics_modules}->{$name}->(
            os => $self->{os},
            centreon_sqlquery => $self->{centreon_sqlquery},
            centstorage_sqlquery => $self->{centstorage_sqlquery},
            sampling => $self->{sampling},
            params => $options{data}->{content},
            logger => $self->{logger}
        );
        next if (!defined($result));
        $metrics->{$name} = $result;
    }

    $self->send_log(
        code => GORGONE_ACTION_FINISH_OK,
        token => $options{token},
        data => {
            message => 'action node finished',
            metrics => $metrics
        }
    );
    $self->{logger}->writeLogDebug('[audit] action node finished');
}

sub action_centreonauditnodelistener {
    my ($self, %options) = @_;

    return 0 if (!defined($options{token}) || $options{token} !~ /^audit-(.*?)-(.*)$/);
    my ($audit_token, $audit_node) = ($1, $2);

    return 0 if (!defined($self->{audit_tokens}->{ $audit_token }) || !defined($self->{audit_tokens}->{ $audit_token }->{nodes}->{ $audit_node }));

    if ($options{data}->{code} == GORGONE_ACTION_FINISH_KO) {
        $self->{logger}->writeLogError("[audit] audit node listener - node '" . $audit_node . "' error");
        $self->{audit_tokens}->{ $audit_token }->{nodes}->{ $audit_node }->{status_code} = 2;
        $self->{audit_tokens}->{ $audit_token }->{nodes}->{ $audit_node }->{status_message} = $options{data}->{data}->{message};
    } elsif ($options{data}->{code} == GORGONE_ACTION_FINISH_OK) {
        $self->{logger}->writeLogDebug("[audit] audit node listener - node '" . $audit_node . "' ok");
        $self->{audit_tokens}->{ $audit_token }->{nodes}->{ $audit_node }->{status_code} = 0;
        $self->{audit_tokens}->{ $audit_token }->{nodes}->{ $audit_node }->{status_message} = 'ok';
        $self->{audit_tokens}->{ $audit_token }->{nodes}->{ $audit_node }->{metrics} = $options{data}->{data}->{metrics};
    } else {
        return 0;
    }
    $self->{audit_tokens}->{ $audit_token }->{done_nodes}++;

    if ($self->{audit_tokens}->{ $audit_token }->{done_nodes} == $self->{audit_tokens}->{ $audit_token }->{count_nodes}) {
        $self->send_log(
            code => GORGONE_ACTION_FINISH_OK,
            token => $audit_token,
            instant => 1,
            data => {
                message => 'finished',
                audit => $self->{audit_tokens}->{ $audit_token }
            }
        );
        delete $self->{audit_tokens}->{ $audit_token };
        return 1;
    }

    my $progress = $self->{audit_tokens}->{ $audit_token }->{done_nodes} * 100 / $self->{audit_tokens}->{ $audit_token }->{count_nodes};
    my $div = int(int($progress) / 5);
    if (int($progress) % 3 == 0) {
        $self->send_log(
            code => GORGONE_MODULE_CENTREON_AUDIT_PROGRESS,
            token => $audit_token,
            instant => 1,
            data => {
                message => 'current progress',
                complete => sprintf('%.2f', $progress) 
            }
        );
    }

    return 1;
}

sub action_centreonauditschedule {
    my ($self, %options) = @_;

    $self->{logger}->writeLogDebug('[audit] starting schedule action');
    $options{token} = $self->generate_token() if (!defined($options{token}));
    $self->send_log(code => GORGONE_ACTION_BEGIN, token => $options{token}, data => { message => 'action schedule proceed' });

    my $params = {};
    
    my ($status, $datas) = $self->{centstorage_sqlquery}->custom_execute(
        request => 'SELECT RRDdatabase_path, RRDdatabase_status_path FROM config',
        mode => 2
    );
    if ($status == -1) {
        $self->send_log(code => GORGONE_ACTION_FINISH_KO, token => $options{token}, data => { message => 'cannot find centstorage config' });
        $self->{logger}->writeLogError('[audit] Cannot find centstorage configuration');
        return 1;
    }
    $params->{rrd_metrics_path} = $datas->[0]->[0];
    $params->{rrd_status_path} = $datas->[0]->[1];

    ($status, $datas) = $self->{centreon_sqlquery}->custom_execute(
        request => "SELECT id, name FROM nagios_server WHERE ns_activate = '1'",
        mode => 2
    );
    if ($status == -1) {
        $self->send_log(code => GORGONE_ACTION_FINISH_KO, token => $options{token}, data => { message => 'cannot find nodes configuration' });
        $self->{logger}->writeLogError('[audit] Cannot find nodes configuration');
        return 1;
    }

    $self->{audit_tokens}->{ $options{token} } = {
        started => time(),
        count_nodes => 0,
        done_nodes => 0,
        nodes => {}
    };
    foreach (@$datas) {
        $self->send_internal_action({
            action => 'ADDLISTENER',
            data => [
                {
                    identity => 'gorgone-audit',
                    event => 'CENTREONAUDITNODELISTENER',
                    token => 'audit-' . $options{token} . '-' . $_->[0],
                    timeout => 300
                }
            ]
        });
        $self->send_internal_action({
            action => 'CENTREONAUDITNODE',
            target => $_->[0],
            token => 'audit-' . $options{token} . '-' . $_->[0],
            data => {
                instant => 1,
                content => $params
            }
        });

        $self->{audit_tokens}->{ $options{token} }->{nodes}->{$_->[0]} = {
            name => $_->[1],
            status_code => 1,
            status_message => 'wip'
        };
        $self->{audit_tokens}->{ $options{token} }->{count_nodes}++;
    }

    return 0;
}

sub sampling {
    my ($self, %options) = @_;

    return if (defined($self->{sampling_last}) && (time() - $self->{sampling_last}) < 60);
    $self->{logger}->writeLogDebug('[audit] sampling starting');
    foreach (keys %{$self->{sampling_modules}}) {
        $self->{sampling_modules}->{$_}->(sampling => $self->{sampling});
    }

    $self->{sampling_last} = time();
}

sub get_system {
    my ($self, %options) = @_;

    $self->{os} = 'unknown';

    my ($rv, $message, $content) = gorgone::standard::misc::slurp(file => '/etc/os-release');
    if ($rv && $content =~ /^ID="(.*?)"/mi) {
        $self->{os} = $1;
        return ;
    }

    my ($error, $stdout, $return_code) = gorgone::standard::misc::backtick(
        command => 'lsb_release -a',
        timeout => 5,
        wait_exit => 1,
        redirect_stderr => 1,
        logger => $options{logger}
    );
    if ($error == 0 && $stdout =~ /^Description:\s+(.*)$/mi) {
        $self->{os} = $1;
    }
}

sub periodic_exec {
    if ($connector->{stop} == 1) {
        $connector->{logger}->writeLogInfo("[audit] $$ has quit");
        exit(0);
    }

    $connector->sampling();
}

sub run {
    my ($self, %options) = @_;

    $self->{internal_socket} = gorgone::standard::library::connect_com(
        context => $self->{zmq_context},
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgone-audit',
        logger => $self->{logger},
        type => $self->get_core_config(name => 'internal_com_type'),
        path => $self->get_core_config(name => 'internal_com_path')
    );
    $self->send_internal_action({
        action => 'CENTREONAUDITREADY',
        data => {}
    });

    if (defined($self->{config_db_centreon})) {
        $self->{db_centreon} = gorgone::class::db->new(
            dsn => $self->{config_db_centreon}->{dsn},
            user => $self->{config_db_centreon}->{username},
            password => $self->{config_db_centreon}->{password},
            force => 0,
            logger => $self->{logger}
        );
        $self->{centreon_sqlquery} = gorgone::class::sqlquery->new(logger => $self->{logger}, db_centreon => $self->{db_centreon});
    }

    if (defined($self->{config_db_centstorage})) {
        $self->{db_centstorage} = gorgone::class::db->new(
            dsn => $self->{config_db_centstorage}->{dsn},
            user => $self->{config_db_centstorage}->{username},
            password => $self->{config_db_centstorage}->{password},
            force => 0,
            logger => $self->{logger}
        );
        $self->{centstorage_sqlquery} = gorgone::class::sqlquery->new(logger => $self->{logger}, db_centreon => $self->{db_centstorage});
    }

    $self->load_modules();
    $self->get_system();

    my $w1 = EV::timer(5, 2, \&periodic_exec);
    my $w2 = EV::io($connector->{internal_socket}->get_fd(), EV::READ, sub { $connector->event() } );
    EV::run();
}

1;
