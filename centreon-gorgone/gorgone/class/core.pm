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

package gorgone::class::core;

use strict;
use warnings;
use POSIX ":sys_wait_h";
use Sys::Hostname;
use ZMQ::LibZMQ4;
use ZMQ::Constants qw(:all);
use gorgone::standard::library;
use gorgone::standard::misc;
use gorgone::class::db;

my ($gorgone);

use base qw(gorgone::class::script);

my $VERSION = '1.0';
my %handlers = (TERM => {}, HUP => {}, CHLD => {});

sub new {
    my $class = shift;
    my $self = $class->SUPER::new('gorgoned',
        centreon_db_conn => 0,
        centstorage_db_conn => 0,
        noconfig => 1
    );

    bless $self, $class;

    $self->{return_child} = {};
    $self->{stop} = 0;
    $self->{internal_register} = {};
    $self->{modules_register} = {};
    $self->{modules_events} = {};
    $self->{modules_id} = {};
    $self->{purge_timer} = time();
    $self->{history_timer} = time();
    $self->{kill_timer} = undef;
    $self->{server_privkey} = undef;
    $self->{register_parent_nodes} = {};
    $self->{counters} = { total => 0, internal => { total => 0 }, external => { total => 0 }, proxy => { total => 0 } };
    $self->{api_endpoints} = {
        'GET_/internal/thumbprint' => 'GETTHUMBPRINT',
        'GET_/internal/constatus' => 'CONSTATUS',
        'GET_/internal/information' => 'INFORMATION',
        'POST_/internal/logger' => 'BCASTLOGGER',
    };
    $self->{config} = 

    return $self;
}

sub init_server_keys {
    my ($self, %options) = @_;

    my ($code, $content_privkey, $content_pubkey);
    $self->{logger}->writeLogInfo("[core] Initialize server keys");

    $self->{keys_loaded} = 0;
    $self->{config} = { configuration => {} } if (!defined($self->{config}->{configuration}));
    $self->{config}->{configuration} = { gorgone => {} } if (!defined($self->{config}->{configuration}->{gorgone}));
    $self->{config}->{configuration}->{gorgone} = { gorgonecore => {} } if (!defined($self->{config}->{configuration}->{gorgone}->{gorgonecore}));

    $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{privkey} = 'keys/rsakey.priv.pem'
        if (!defined($self->{config}->{configuration}->{gorgone}->{gorgonecore}->{privkey}) || $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{privkey} eq '');
    $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{pubkey} = 'keys/rsakey.pub.pem'
        if (!defined($self->{config}->{configuration}->{gorgone}->{gorgonecore}->{pubkey}) || $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{pubkey} eq '');

    if (! -f $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{privkey} && ! -f $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{pubkey}) {
        ($code, $content_privkey, $content_pubkey) = gorgone::standard::library::generate_keys(logger => $self->{logger});
        return if ($code == 0);
        $code = gorgone::standard::misc::write_file(
            logger => $self->{logger},
            filename => $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{privkey},
            content => $content_privkey,
        );
        return if ($code == 0);
        $self->{logger}->writeLogInfo("[core] Private key file '$self->{config}->{configuration}->{gorgone}->{gorgonecore}->{privkey}' written");
        
        $code = gorgone::standard::misc::write_file(
            logger => $self->{logger},
            filename => $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{pubkey},
            content => $content_pubkey,
        );
        return if ($code == 0);
        $self->{logger}->writeLogInfo("[core] Public key file '$self->{config}->{configuration}->{gorgone}->{gorgonecore}->{pubkey}' written");
    }

    ($code, $self->{server_privkey}) = gorgone::standard::library::loadprivkey(
        logger => $self->{logger},
        privkey => $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{privkey},
        noquit => 1
    );
    return if ($code == 0);
    $self->{logger}->writeLogInfo("[core] Private key file '$self->{config}->{configuration}->{gorgone}->{gorgonecore}->{privkey}' loaded");

    ($code, $self->{server_pubkey}) = gorgone::standard::library::loadpubkey(
        logger => $self->{logger},
        pubkey => $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{pubkey},
        noquit => 1
    );
    return if ($code == 0);
    $self->{logger}->writeLogInfo("[core] Public key file '$self->{config}->{configuration}->{gorgone}->{gorgonecore}->{pubkey}' loaded");

    $self->{keys_loaded} = 1;
}

sub init {
    my ($self) = @_;
    $self->SUPER::init();

    # redefine to avoid out when we try modules
    $SIG{__DIE__} = undef;

    ## load config
    if (!defined($self->{config_file})) {
        $self->{logger}->writeLogError('[core] please define config file option');
        exit(1);
    }
    if (! -f $self->{config_file}) {
        $self->{logger}->writeLogError("[core] can't find config file '$self->{config_file}'");
        exit(1);
    }
    $self->{config} = $self->yaml_load_config(
        file => $self->{config_file},
        filter => '!($ariane eq "configuration##" || $ariane =~ /^configuration##(?:gorgone|centreon)##/)'
    );
    $self->init_server_keys();

    $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_type} = 'ipc'
        if (!defined($self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_type}) || $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_type} eq '');
    $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_path} = '/tmp/gorgone/routing.ipc'
        if (!defined($self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_path}) || $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_path} eq '');
    $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{timeout} = 
        defined($self->{config}->{configuration}->{gorgone}->{gorgonecore}->{timeout}) && $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{timeout} =~ /(\d+)/ ? $1 : 50;

    $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{cipher} = 'Cipher::AES'
        if (!defined($self->{config}->{configuration}->{gorgone}->{gorgonecore}->{cipher}) || $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{cipher} eq '');
    $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{keysize} = 32
        if (!defined($self->{config}->{configuration}->{gorgone}->{gorgonecore}->{keysize}) || $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{keysize} eq '');
    $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{vector} = '0123456789012345'
        if (!defined($self->{config}->{configuration}->{gorgone}->{gorgonecore}->{vector}) || $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{vector} eq '');

    $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{fingerprint_mode} =
        defined($self->{config}->{configuration}->{gorgone}->{gorgonecore}->{fingerprint_mode}) && $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{fingerprint_mode} =~ /^\s*(always|firt|strict)\s*/i ? lc($1) : 'first';
    $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{fingerprint_mgr} = {} if (!defined($self->{config}->{configuration}->{gorgone}->{gorgonecore}->{fingerprint_mgr}));
    $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{fingerprint_mgr}->{package} = 'gorgone::class::fingerprint::backend::sql'
        if (!defined($self->{config}->{configuration}->{gorgone}->{gorgonecore}->{fingerprint_mgr}->{package}) || $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{fingerprint_mgr}->{package} eq '');

    $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{fingerprint_mode} =
        defined($self->{config}->{configuration}->{gorgone}->{gorgonecore}->{fingerprint_mode}) && $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{fingerprint_mode} =~ /^\s*(always|firt|strict)\s*/i ? lc($1) : 'first';

    $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{gorgone_db_type} =
        defined($self->{config}->{configuration}->{gorgone}->{gorgonecore}->{gorgone_db_type}) && $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{gorgone_db_type} ne '' ? $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{gorgone_db_type} : 'SQLite';
    $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{gorgone_db_name} =
        defined($self->{config}->{configuration}->{gorgone}->{gorgonecore}->{gorgone_db_name}) && $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{gorgone_db_name} ne '' ? $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{gorgone_db_name} : 'dbname=/var/lib/centreon-gorgone/history.sdb';
    $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{gorgone_db_autocreate_schema} =
        defined($self->{config}->{configuration}->{gorgone}->{gorgonecore}->{gorgone_db_autocreate_schema}) && $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{gorgone_db_autocreate_schema} =~ /(\d+)/ ? $1 : 1;
    gorgone::standard::library::init_database(
        gorgone => $gorgone,
        type => $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{gorgone_db_type},
        db => $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{gorgone_db_name},
        host => $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{gorgone_db_host},
        port => $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{gorgone_db_port},
        user => $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{gorgone_db_user},
        password => $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{gorgone_db_password},
        autocreate_schema => $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{gorgone_db_autocreate_schema},
        force => 2,
        logger => $gorgone->{logger}
    );
    
    $self->{hostname} = $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{hostname};
    if (!defined($self->{hostname}) || $self->{hostname} eq '') {
        $self->{hostname} = hostname();
    }

    $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{proxy_name} = 
        (defined($self->{config}->{configuration}->{gorgone}->{gorgonecore}->{proxy_name}) && $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{proxy_name} ne '') ? $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{proxy_name} : 'proxy';
    $self->{id} = $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{id};

    $self->load_modules();
    
    $self->set_signal_handlers();
}

sub set_signal_handlers {
    my ($self) = @_;

    $SIG{TERM} = \&class_handle_TERM;
    $handlers{TERM}->{$self} = sub { $self->handle_TERM() };
    $SIG{HUP} = \&class_handle_HUP;
    $handlers{HUP}->{$self} = sub { $self->handle_HUP() };
    $SIG{CHLD} = \&class_handle_CHLD;
    $handlers{CHLD}->{$self} = sub { $self->handle_CHLD() };
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

sub class_handle_CHLD {
    foreach (keys %{$handlers{CHLD}}) {
        &{$handlers{CHLD}->{$_}}();
    }
}

sub handle_TERM {
    my ($self) = @_;
    $self->{logger}->writeLogInfo("[core] $$ Receiving order to stop...");
    $self->{stop} = 1;
    
    foreach my $name (keys %{$self->{modules_register}}) {
        $self->{modules_register}->{$name}->{gently}->(logger => $self->{logger});
    }
    $self->{kill_timer} = time();
}

sub handle_HUP {
    my $self = shift;
    $self->{logger}->writeLogInfo("[core] $$ Receiving order to reload...");
    # TODO
}

sub handle_CHLD {
    my $self = shift;
    my $child_pid;

    while (($child_pid = waitpid(-1, &WNOHANG)) > 0) {
        $self->{logger}->writeLogDebug("[core] Received SIGCLD signal (pid: $child_pid)");
        $self->{return_child}->{$child_pid} = time();
    }
    
    $SIG{CHLD} = \&class_handle_CHLD;
}

sub unload_module {
    my ($self, %options) = @_;

    foreach my $event (keys %{$self->{modules_events}}) {
        if ($self->{modules_events}->{$event}->{module}->{package} eq $options{package}) {
            delete $self->{modules_events}->{$event};
        }
    }

    delete $self->{modules_register}->{ $options{package} };
    foreach (keys %{$self->{modules_id}}) {
        if ($self->{modules_id}->{$_} eq $options{package}) {
            delete $self->{modules_id}->{$_};
            last;
        }
    }
    $self->{logger}->writeLogInfo("[core] Module '" . $options{package} . "' is unloaded");
}

sub load_module {
    my ($self, %options) = @_;

    if (!defined($options{config_module}->{name}) || $options{config_module}->{name} eq '') {
        $self->{logger}->writeLogError('[core] No module name');
        return 0;
    }
    if (!defined($options{config_module}->{package}) || $options{config_module}->{package} eq '') {
        $self->{logger}->writeLogError('[core] No package name');
        return 0;
    }
    if (defined($self->{modules_register}->{ $options{config_module}->{package} })) {
        $self->{logger}->writeLogError("[core] Package '$options{config_module}->{package}' already loaded");
        return 0;
    }
    
    return 0 if (!defined($options{config_module}->{enable}) || $options{config_module}->{enable} eq 'false');
    $self->{logger}->writeLogInfo("[core] Module '" . $options{config_module}->{name} . "' is loading");

    my $package = $options{config_module}->{package};
    (my $file = "$package.pm") =~ s{::}{/}g;
    eval {
        local $SIG{__DIE__} = 'IGNORE';
        require $file;
    };
    if ($@) {
        $self->{logger}->writeLogInfo("[core] Module '" . $options{config_module}->{name} . "' cannot be loaded: " . $@);
        return 0;
    }
    $self->{modules_register}->{$package} = {};

    foreach my $method_name (('register', 'routing', 'kill', 'kill_internal', 'gently', 'check', 'init', 'broadcast')) {
        unless ($self->{modules_register}->{$package}->{$method_name} = $package->can($method_name)) {
            $self->{logger}->writeLogError("[core] No function '$method_name' for module '" . $options{config_module}->{name} . "'");
            return 0;
        }
    }

    my ($loaded, $namespace, $name, $events) = $self->{modules_register}->{$package}->{register}->(
        config => $options{config_module},
        config_core => $self->{config}->{configuration}->{gorgone}->{gorgonecore},
        config_db_centreon => $self->{config}->{configuration}->{centreon}->{database}->{db_configuration},
        config_db_centstorage => $self->{config}->{configuration}->{centreon}->{database}->{db_realtime},
        logger => $self->{logger},
    );
    if ($loaded == 0) {
        $self->{logger}->writeLogError("[core] Module '" . $options{config_module}->{name} . "' cannot be loaded");
        return 0;
    }

    $self->{modules_id}->{$name} = $package;

    foreach my $event (@$events) {
        $self->{modules_events}->{$event->{event}} = {
            module => {
                namespace => $namespace,
                name => $name,
                package => $package
            }
        };
        $self->{api_endpoints}->{$event->{method} . '_/' . $namespace . '/' . $name . $event->{uri}} = $event->{event} if defined($event->{uri});
    }

    $self->{logger}->writeLogInfo("[core] Module '" . $options{config_module}->{name} . "' is loaded");
    return 1;
}

sub load_modules {
    my ($self) = @_;
    return if (!defined($self->{config}->{configuration}->{gorgone}->{modules}));

    foreach my $module (@{$self->{config}->{configuration}->{gorgone}->{modules}}) {
        $self->load_module(config_module => $module);
    }

    # force to load module dbclean
    $self->load_module(config_module => { name => 'dbcleaner', package => 'gorgone::modules::core::dbcleaner::hooks', enable => 'true' });

    # Load internal functions
    foreach my $method_name (('putlog', 'getlog', 'kill', 'ping', 
        'getthumbprint', 'constatus', 'setcoreid', 'synclogs', 'loadmodule', 'unloadmodule', 'information')) {
        unless ($self->{internal_register}->{$method_name} = gorgone::standard::library->can($method_name)) {
            $self->{logger}->writeLogError("[core] No function '$method_name'");
            exit(1);
        }
    }
}

sub broadcast_run {
    my ($self, %options) = @_;

    if ($options{action} eq 'BCASTLOGGER') {
        my $data = gorgone::standard::library::json_decode(data => $options{data}, logger => $self->{logger});
        return if (!defined($data));

        if (defined($data->{content}->{severity}) && $data->{content}->{severity} ne '') {
            if ($data->{content}->{severity} eq 'default') {
                $self->{logger}->set_default_severity();
            } else {
                $self->{logger}->severity($data->{content}->{severity});
            }
        }
    }

    foreach (keys %{$self->{modules_register}}) {
        $self->{modules_register}->{$_}->{broadcast}->(
            socket => $self->{internal_socket},
            action => $options{action},
            logger => $self->{logger},
            data => $options{data},
            token => $options{token}
        );
    }
}

sub message_run {
    my ($self, %options) = @_;

    $self->{logger}->writeLogDebug('[core] Message received - ' . $options{message});
    if ($options{message} !~ /^\[(.+?)\]\s+\[(.*?)\]\s+\[(.*?)\]\s+(.*)$/) {
        return (undef, 1, { message => 'request not well formatted' });
    }
    my ($action, $token, $target, $data) = ($1, $2, $3, $4);

    # Check if not myself ;)
    if (defined($target) && ($target eq '' || (defined($self->{id}) && $target eq $self->{id}))) {
        $target = undef;
    }

    if (!defined($token) || $token eq '') {
        $token = gorgone::standard::library::generate_token();
    }

    if ($action !~ /^(?:PUTLOG|GETLOG|KILL|PING|CONSTATUS|SETCOREID|SYNCLOGS|LOADMODULE|UNLOADMODULE|INFORMATION|GETTHUMBPRINT|BCAST.*)$/ && 
        !defined($target) && !defined($self->{modules_events}->{$action})) {
        gorgone::standard::library::add_history(
            dbh => $self->{db_gorgone},
            code => 1,
            token => $token,
            data => { error => "unknown_action", message => "action '$action' is unknown" },
            json_encode => 1
        );
        return (undef, 1, { error => "unknown_action", message => "action '$action' is unknown" });
    }

    $self->{counters}->{$options{router_type}}->{lc($action)} = 0 if (!defined($self->{counters}->{$options{router_type}}->{lc($action)}));
    $self->{counters}->{$options{router_type}}->{lc($action)}++;
    $self->{counters}->{total}++;
    $self->{counters}->{$options{router_type}}->{total}++;

    if ($self->{stop} == 1) {
        gorgone::standard::library::add_history(
            dbh => $self->{db_gorgone},
            code => 0,
            token => $token,
            data => { message => 'gorgone is stopping/restarting. Cannot proceed request.' },
            json_encode => 1
        );
        return ($token, 1, { message => 'gorgone is stopping/restarting. Cannot proceed request.' });
    }
    
    # Check Routing
    if (defined($target)) {
        if (!defined($self->{modules_id}->{ $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{proxy_name} }) || 
            !defined($self->{modules_register}->{ $self->{modules_id}->{ $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{proxy_name} } })) {
            gorgone::standard::library::add_history(
                dbh => $self->{db_gorgone},
                code => 1,
                token => $token,
                data => { error => "no_proxy", message => 'no proxy configured. cannot manage target.' },
                json_encode => 1
            );
            return ($token, 1, { error => "no_proxy", message => 'no proxy configured. cannot manage target.' });
        }

        $self->{counters}->{proxy}->{lc($action)} = 0 if (!defined($self->{counters}->{proxy}->{lc($action)}));
        $self->{counters}->{proxy}->{lc($action)}++;
        $self->{counters}->{proxy}->{total}++;

        $self->{modules_register}->{ $self->{modules_id}->{ $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{proxy_name} } }->{routing}->(
            socket => $self->{internal_socket},
            dbh => $self->{db_gorgone},
            logger => $self->{logger}, 
            action => $action,
            token => $token,
            target => $target,
            data => $data,
            hostname => $self->{hostname}
        );
        return ($token, 0);
    }
    
    if ($action =~ /^(?:PUTLOG|GETLOG|KILL|PING|CONSTATUS|SETCOREID|SYNCLOGS|LOADMODULE|UNLOADMODULE|INFORMATION|GETTHUMBPRINT)$/) {
        my ($code, $response, $response_type) = $self->{internal_register}->{lc($action)}->(
            gorgone => $self,
            gorgone_config => $self->{config}->{configuration}->{gorgone},
            identity => $options{identity},
            router_type => $options{router_type},
            id => $self->{id},
            data => $data,
            token => $token,
            logger => $self->{logger}
        );

        if ($action =~ /^(?:CONSTATUS|INFORMATION|GETTHUMBPRINT)$/) {
            gorgone::standard::library::add_history(
                dbh => $self->{db_gorgone},
                code => $code,
                token => $token,
                data => $response,
                json_encode => 1
            );
        }
        
        return ($token, $code, $response, $response_type);
    } elsif ($action =~ /^BCAST(.*)$/) {
        return (undef, 1, { message => "action '$action' is not known" }) if ($1 !~ /^LOGGER$/);
        $self->broadcast_run(
            action => $action,
            data => $data,
            token => $token
        );
    } else {
        $self->{modules_register}->{$self->{modules_events}->{$action}->{module}->{package}}->{routing}->(
            socket => $self->{internal_socket}, 
            dbh => $self->{db_gorgone},
            logger => $self->{logger},
            action => $action,
            token => $token,
            target => $target,
            data => $data,
            hostname => $self->{hostname}
        );
    }
    return ($token, 0);
}

sub router_internal_event {
    while (1) {
        my ($identity, $message) = gorgone::standard::library::zmq_read_message(socket => $gorgone->{internal_socket});
        my ($token, $code, $response, $response_type) = $gorgone->message_run(
            message => $message,
            identity => $identity,
            router_type => 'internal',
        );
        gorgone::standard::library::zmq_core_response(
            socket => $gorgone->{internal_socket},
            identity => $identity,
            response_type => $response_type,
            data => $response,
            code => $code,
            token => $token
        );
        last unless (gorgone::standard::library::zmq_still_read(socket => $gorgone->{internal_socket}));
    }
}

sub handshake {
    my ($self, %options) = @_;

    my ($identity, $message) = gorgone::standard::library::zmq_read_message(socket => $self->{external_socket});

    # Test if it asks for the pubkey
    if ($message =~ /^\s*\[GETPUBKEY\]/) {
        gorgone::standard::library::zmq_core_pubkey_response(
            socket => $self->{external_socket},
            identity => $identity,
            pubkey => $self->{server_pubkey}
        );
        return undef;
    }

    my ($status, $key) = gorgone::standard::library::is_handshake_done(dbh => $self->{db_gorgone}, identity => $identity);

    if ($status == 1) {
        ($status, my $response) = gorgone::standard::library::uncrypt_message(
            cipher => $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{cipher}, 
            message => $message,
            symkey => $key,
            vector => $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{vector}
        );
        if ($status == 0 && $response =~ /^\[.*\]/) {
            gorgone::standard::library::update_identity(dbh => $self->{db_gorgone}, identity => $identity);
            return ($identity, $key, $response);
        }
        
        # Maybe he want to redo a handshake
        $status = 0;    
    }
    
    if ($status == -1) {
        gorgone::standard::library::zmq_core_response(
            socket => $self->{external_socket}, 
            identity => $identity,
            code => 1,
            data => { message => 'Database issue' }
        );
        return undef;
    } elsif ($status == 0) {
        # We try to uncrypt
        ($status, my $client_pubkey) = gorgone::standard::library::is_client_can_connect(
            privkey => $self->{server_privkey},
            message => $message,
            logger => $self->{logger},
            authorized_clients => $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{authorized_clients}
        );
        if ($status == -1) {
            gorgone::standard::library::zmq_core_response(
                socket => $self->{external_socket},
                identity => $identity,
                code => 1,
                data => { message => 'handshake issue' }
            );
            return undef;
        }
        my ($status, $symkey) = gorgone::standard::library::generate_symkey(
            logger => $self->{logger},
            cipher => $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{cipher},
            keysize => $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{keysize}
        );
        if ($status == -1) {
            gorgone::standard::library::zmq_core_response(
                socket => $self->{external_socket}, identity => $identity,
                code => 1, data => { message => 'handshake issue' }
            );
        }
        if (gorgone::standard::library::add_identity(dbh => $self->{db_gorgone}, identity => $identity, symkey => $symkey) == -1) {
            gorgone::standard::library::zmq_core_response(
                socket => $self->{external_socket}, identity => $identity,
                code => 1, data => { message => 'handshake issue' }
            );
        }

        if (gorgone::standard::library::zmq_core_key_response(
                logger => $self->{logger}, socket => $self->{external_socket}, identity => $identity,
                client_pubkey => $client_pubkey, hostname => $self->{hostname}, symkey => $symkey) == -1
            ) {
            gorgone::standard::library::zmq_core_response(
                socket => $self->{external_socket}, identity => $identity,
                code => 1, data => { message => 'handshake issue' }
            );
        }
        return undef;
    }    
}

sub send_message_parent {
    my (%options) = @_;

    if ($options{router_type} eq 'internal') {
        gorgone::standard::library::zmq_core_response(
            socket => $gorgone->{internal_socket},
            identity => $options{identity},
            response_type => $options{response_type},
            data => $options{data},
            code => $options{code},
            token => $options{token}
        );
    }
    if ($options{router_type} eq 'external') {
        my ($status, $key) = gorgone::standard::library::is_handshake_done(dbh => $gorgone->{db_gorgone}, identity => $options{identity});
        return if ($status == 0);
        gorgone::standard::library::zmq_core_response(
            socket => $gorgone->{external_socket},
            identity => $options{identity},
            response_type => $options{response_type},
            cipher => $gorgone->{config}->{configuration}->{gorgone}->{gorgonecore}->{cipher},
            vector => $gorgone->{config}->{configuration}->{gorgone}->{gorgonecore}->{vector},
            symkey => $key,
            token => $options{token},
            code => $options{code},
            data => $options{data}
        );
    }
}

sub router_external_event {
    while (1) {
        my ($identity, $key, $message) = $gorgone->handshake();
        if (defined($message)) {
            my ($token, $code, $response, $response_type) = $gorgone->message_run(
                message => $message,
                identity => $identity,
                router_type => 'external',
            );
            gorgone::standard::library::zmq_core_response(
                socket => $gorgone->{external_socket},
                identity => $identity, response_type => $response_type,
                cipher => $gorgone->{config}->{configuration}->{gorgone}->{gorgonecore}->{cipher},
                vector => $gorgone->{config}->{configuration}->{gorgone}->{gorgonecore}->{vector},
                symkey => $key,
                token => $token, code => $code,
                data => $response
            );
        }
        last unless (gorgone::standard::library::zmq_still_read(socket => $gorgone->{external_socket}));
    }
}

sub waiting_ready_pool {
    my (%options) = @_;

    my $time = time();
    # We wait 10 seconds
    while (time() - $time < 10) {
        foreach my $pool_id (keys %{$options{pool}})  {
            return 1 if ($options{pool}->{$pool_id}->{ready} == 1);
        }
        zmq_poll($gorgone->{poll}, 5000);
    }
    foreach my $pool_id (keys %{$options{pool}})  {
        return 1 if ($options{pool}->{$pool_id}->{ready} == 1);
    }

    return 0;
}

sub waiting_ready {
    my (%options) = @_;

    return 1 if (${$options{ready}} == 1);
    
    my $time = time();
    # We wait 10 seconds
    while (${$options{ready}} == 0 && 
           time() - $time < 10) {
        zmq_poll($gorgone->{poll}, 5000);
    }

    if (${$options{ready}} == 0) {
        return 0;
    }

    return 1;
}

sub quit {
    my ($self, %options) = @_;
    
    $self->{logger}->writeLogInfo("[core] Quit main process");
    zmq_close($self->{internal_socket});
    if (defined($self->{external_socket})) {
        zmq_close($self->{external_socket});
    }
    exit(0);
}

sub run {
    $gorgone = shift;

    $gorgone->SUPER::run();
    $gorgone->{logger}->redirect_output();

    $gorgone->{logger}->writeLogInfo("[core] Gorgoned started");
    $gorgone->{logger}->writeLogInfo("[core] PID $$");

    if (gorgone::standard::library::add_history(
        dbh => $gorgone->{db_gorgone},
        code => 0,
        data => { message => 'gorgoned is starting...' },
        json_encode => 1) == -1
    ) {
        $gorgone->{logger}->writeLogInfo("[core] Cannot write in history. We quit!!");
        exit(1);
    }
    
    $gorgone->{internal_socket} = gorgone::standard::library::create_com(
        type => $gorgone->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_type},
        path => $gorgone->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_path},
        zmq_type => 'ZMQ_ROUTER', name => 'router-internal',
        logger => $gorgone->{logger}
    );
    if (defined($gorgone->{config}->{configuration}->{gorgone}->{gorgonecore}->{external_com_type}) && $gorgone->{config}->{configuration}->{gorgone}->{gorgonecore}->{external_com_type} ne '') {
        if ($gorgone->{keys_loaded}) {
            $gorgone->{external_socket} = gorgone::standard::library::create_com(
                type => $gorgone->{config}->{configuration}->{gorgone}->{gorgonecore}->{external_com_type},
                path => $gorgone->{config}->{configuration}->{gorgone}->{gorgonecore}->{external_com_path},
                zmq_type => 'ZMQ_ROUTER', name => 'router-external',
                logger => $gorgone->{logger}
            );
        } else {
            $gorgone->{logger}->writeLogError("[core] Cannot create external com: no keys loaded");
        }
    }

    # Initialize poll set
    $gorgone->{poll} = [
        {
            socket  => $gorgone->{internal_socket},
            events  => ZMQ_POLLIN,
            callback => \&router_internal_event,
        }
    ];
    
    if (defined($gorgone->{external_socket})) {
        push @{$gorgone->{poll}}, {
            socket  => $gorgone->{external_socket},
            events  => ZMQ_POLLIN,
            callback => \&router_external_event,
        };
    }
    
    # init all modules
    foreach my $name (keys %{$gorgone->{modules_register}}) {
        $gorgone->{logger}->writeLogDebug("[core] Call init function from module '$name'");
        $gorgone->{modules_register}->{$name}->{init}->(
            id => $gorgone->{id},
            logger => $gorgone->{logger},
            poll => $gorgone->{poll},
            external_socket => $gorgone->{external_socket},
            internal_socket => $gorgone->{internal_socket},
            dbh => $gorgone->{db_gorgone},
            api_endpoints => $gorgone->{api_endpoints}
        );
    }
    
    $gorgone->{logger}->writeLogInfo("[core] Server accepting clients");
    my $cb_timer_check = time();
    while (1) {
        my $count = 0;
        my $poll = [@{$gorgone->{poll}}];

        my $current_time = time();
        if (time() - $cb_timer_check > 15) {
            foreach my $name (keys %{$gorgone->{modules_register}}) {
                my $count_module = $gorgone->{modules_register}->{$name}->{check}->(
                    logger => $gorgone->{logger},
                    dead_childs => $gorgone->{return_child},
                    internal_socket => $gorgone->{internal_socket},
                    dbh => $gorgone->{db_gorgone},
                    poll => $poll,
                    api_endpoints => $gorgone->{api_endpoints},
                );
                $cb_timer_check = time();
                $count += $count_module;
                if ($count_module == 0) {
                    $gorgone->unload_module(package => $name);
                }
            }

            # We can clean return_child.
            $gorgone->{return_child} = {};
        }

        if ($gorgone->{stop} == 1) {
            # No childs
            if ($count == 0) {
                $gorgone->quit();
            }
            
            # Send KILL
            if (time() - $gorgone->{kill_timer} > $gorgone->{config}->{configuration}->{gorgone}->{gorgonecore}->{timeout}) {
                foreach my $name (keys %{$gorgone->{modules_register}}) {
                    $gorgone->{modules_register}->{$name}->{kill_internal}->(logger => $gorgone->{logger});
                }
                $gorgone->quit();
            }
        }

        zmq_poll($poll, 5000);
    }
}

1;

__END__
