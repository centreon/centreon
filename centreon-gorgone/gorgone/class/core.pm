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
use MIME::Base64;
use Crypt::Mode::CBC;
use ZMQ::FFI qw(ZMQ_DONTWAIT ZMQ_SNDMORE ZMQ_POLLIN);
use EV;
use gorgone::standard::library;
use gorgone::standard::constants qw(:all);
use gorgone::standard::misc;
use gorgone::class::db;
use gorgone::class::listener;
use gorgone::class::frame;
use Time::HiRes;
use Try::Tiny;

my ($gorgone);

use base qw(gorgone::class::script);

my $VERSION = '22.04.0';
my %handlers = (TERM => {}, HUP => {}, CHLD => {}, DIE => {});

sub new {
    my $class = shift;
    my $self = $class->SUPER::new(
        'gorgoned',
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
    $self->{sigterm_start_time} = undef;
    $self->{sigterm_last_time} = undef;
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

sub get_version {
    my ($self, %options) = @_;

    return $VERSION;
}

sub init_server_keys {
    my ($self, %options) = @_;

    my ($code, $content_privkey, $content_pubkey);
    $self->{logger}->writeLogInfo("[core] Initialize server keys");

    $self->{keys_loaded} = 0;
    $self->{config} = { configuration => {} } if (!defined($self->{config}->{configuration}));
    $self->{config}->{configuration} = { gorgone => {} } if (!defined($self->{config}->{configuration}->{gorgone}));
    $self->{config}->{configuration}->{gorgone}->{gorgonecore} = {} if (!defined($self->{config}->{configuration}->{gorgone}->{gorgonecore}));

    $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{privkey} = '/var/lib/centreon-gorgone/.keys/rsakey.priv.pem'
        if (!defined($self->{config}->{configuration}->{gorgone}->{gorgonecore}->{privkey}) || $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{privkey} eq '');
    $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{pubkey} = '/var/lib/centreon-gorgone/.keys/rsakey.pub.pem'
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

    my $rv = chmod(0600, $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{privkey});
    if ($rv == 0) {
        $self->{logger}->writeLogInfo("[core] chmod private key file '$self->{config}->{configuration}->{gorgone}->{gorgonecore}->{privkey}': $!");
    }
    $rv = chmod(0640, $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{pubkey});
    if ($rv == 0) {
        $self->{logger}->writeLogInfo("[core] chmod public key file '$self->{config}->{configuration}->{gorgone}->{gorgonecore}->{pubkey}': $!");
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

    $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{external_com_zmq_tcp_keepalive} =
        defined($self->{config}->{configuration}->{gorgone}->{gorgonecore}->{external_com_zmq_tcp_keepalive}) && $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{external_com_zmq_tcp_keepalive} =~ /^(0|1)$/ ? $1 : 1;

    my $time_hi = Time::HiRes::time();
    $time_hi =~ s/\.//;
    $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_type} = 'ipc'
        if (!defined($self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_type}) || $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_type} eq '');
    $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_path} = '/tmp/gorgone/routing-' . $time_hi . '.ipc'
        if (!defined($self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_path}) || $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_path} eq '');

    if (defined($self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_crypt}) && $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_crypt} =~ /^(?:false|0)$/i) {
        $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_crypt} = 0;
    } else {
        $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_crypt} = 1;
    }

    $self->{internal_crypt} = { enabled => 0 };
    if ($self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_crypt} == 1) {
        $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_cipher} = 'AES'
            if (!defined($self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_cipher}) || $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_cipher} eq '');
        $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_padding} = 1 # PKCS5 padding
            if (!defined($self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_padding}) || $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_padding} eq '');
        $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_keysize} = 32
            if (!defined($self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_keysize}) || $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_keysize} eq '');
        $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_rotation} = 1440 # minutes
            if (!defined($self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_rotation}) || $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_rotation} eq '');
        $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_rotation} *= 60;

        $self->{cipher} = Crypt::Mode::CBC->new(
            $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_cipher},
            $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_padding}
        );

        my ($rv, $symkey, $iv);
        ($rv, $symkey) = gorgone::standard::library::generate_symkey(
            keysize => $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_keysize}
        );
        ($rv, $iv) = gorgone::standard::library::generate_symkey(
            keysize => 16
        );
        $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_core_key_ctime} = time();
        $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_core_key} = $symkey;
        $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_core_oldkey} = undef;
        $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_identity_keys} = {};
        $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_iv} = $iv;

        $self->{internal_crypt} = {
            enabled => 1,
            cipher => $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_cipher},
            padding => $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_padding},
            iv => $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_iv}
        };
    }

    $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{timeout} = 
        defined($self->{config}->{configuration}->{gorgone}->{gorgonecore}->{timeout}) && $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{timeout} =~ /(\d+)/ ? $1 : 50;

    $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{external_com_cipher} = 'AES'
        if (!defined($self->{config}->{configuration}->{gorgone}->{gorgonecore}->{external_com_cipher}) || $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{external_com_cipher} eq '');
    $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{external_com_padding} = 1 # PKCS5 padding
        if (!defined($self->{config}->{configuration}->{gorgone}->{gorgonecore}->{external_com_padding}) || $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{external_com_padding} eq '');
    $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{external_com_keysize} = 32
        if (!defined($self->{config}->{configuration}->{gorgone}->{gorgonecore}->{external_com_keysize}) || $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{external_com_keysize} eq '');
    $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{external_com_rotation} = 1440 # minutes
        if (!defined($self->{config}->{configuration}->{gorgone}->{gorgonecore}->{external_com_rotation}) || $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{external_com_rotation} eq '');
    $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{external_com_rotation} *= 60;

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
        version => $self->get_version(),
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

sub init_external_informations {
    my ($self) = @_;

    my ($status, $sth) = $self->{db_gorgone}->query({
        query => "SELECT `identity`, `ctime`, `mtime`, `key`, `oldkey`, `iv`, `oldiv` FROM gorgone_identity ORDER BY id DESC"
    });
    if ($status == -1) {
        $self->{logger}->writeLogError("[core] cannot load gorgone_identity");
        return 0;
    }

    $self->{identity_infos} = {};
    while (my $row = $sth->fetchrow_arrayref()) {
        next if (!defined($row->[3]) || !defined($row->[2]));

        if (!defined($self->{identity_infos}->{ $row->[0] })) {
            $self->{identity_infos}->{ $row->[0] } = {
                ctime => $row->[1],
                mtime => $row->[2],
                key => pack('H*', $row->[3]),
                oldkey => defined($row->[4]) ? pack('H*', $row->[4]) : undef,
                iv => pack('H*', $row->[5]),
                oldiv => defined($row->[6]) ? pack('H*', $row->[6]) : undef
            };
        }
    }

    $self->{external_crypt_mode} = Crypt::Mode::CBC->new(
        $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{external_com_cipher},
        $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{external_com_padding}
    );
}

sub set_signal_handlers {
    my ($self) = @_;

    $SIG{TERM} = \&class_handle_TERM;
    $handlers{TERM}->{$self} = sub { $self->handle_TERM() };
    $SIG{HUP} = \&class_handle_HUP;
    $handlers{HUP}->{$self} = sub { $self->handle_HUP() };
    $SIG{CHLD} = \&class_handle_CHLD;
    $handlers{CHLD}->{$self} = sub { $self->handle_CHLD() };
    $SIG{__DIE__} = \&class_handle_DIE;
    $handlers{DIE}->{$self} = sub { $self->handle_DIE($_[0]) };
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

sub class_handle_DIE {
    my ($msg) = @_;

    foreach (keys %{$handlers{DIE}}) {
        &{$handlers{DIE}->{$_}}($msg);
    }
}

sub handle_TERM {
    my ($self) = @_;
    $self->{logger}->writeLogInfo("[core] $$ Receiving order to stop...");

    $self->{stop} = 1;
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

sub handle_DIE {
    my $self = shift;
    my $msg = shift;

    $self->{logger}->writeLogError("[core] Receiving DIE: $msg");
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
            delete $self->{modules_register}->{$package};
            $self->{logger}->writeLogError("[core] No function '$method_name' for module '" . $options{config_module}->{name} . "'");
            return 0;
        }
    }

    my ($loaded, $namespace, $name, $events) = $self->{modules_register}->{$package}->{register}->(
        config => $options{config_module},
        config_core => $self->{config}->{configuration}->{gorgone},
        config_db_centreon => $self->{config}->{configuration}->{centreon}->{database}->{db_configuration},
        config_db_centstorage => $self->{config}->{configuration}->{centreon}->{database}->{db_realtime},
        logger => $self->{logger}
    );
    if ($loaded == 0) {
        delete $self->{modules_register}->{$package};
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
    foreach my $method_name (('addlistener', 'putlog', 'getlog', 'kill', 'ping', 
        'getthumbprint', 'constatus', 'setcoreid', 'synclogs', 'loadmodule', 'unloadmodule', 'information', 'setmodulekey')) {
        unless ($self->{internal_register}->{$method_name} = gorgone::standard::library->can($method_name)) {
            $self->{logger}->writeLogError("[core] No function '$method_name'");
            exit(1);
        }
    }
}

sub broadcast_core_key {
    my ($self, %options) = @_;

    my ($rv, $key) = gorgone::standard::library::generate_symkey(
        keysize => $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_keysize}
    );

    my $message = '[BCASTCOREKEY] [] [] { "key": "' . unpack('H*', $key). '"}';
    my $frame = gorgone::class::frame->new();
    $frame->setFrame(\$message);

    $self->message_run(
        {
            frame => $frame,
            router_type => 'internal'
        }
    );
}

sub read_internal_message {
    my ($self, %options) = @_;

    my ($identity, $frame) = gorgone::standard::library::zmq_read_message(
        socket => $self->{internal_socket},
        logger => $self->{logger}
    );
    return undef if (!defined($identity));

    if ($self->{internal_crypt}->{enabled} == 1) {
        my $id = pack('H*', $identity);
        my $keys;
        if (defined($self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_identity_keys}->{$id})) {
            $keys = [ $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_identity_keys}->{$id}->{key} ];
        } else {
            $keys = [ $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_core_key} ];
            push @$keys, $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_core_oldkey}
                if (defined($self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_core_oldkey}));
        }
        foreach my $key (@$keys) {
            if ($frame->decrypt({ cipher => $self->{cipher}, key => $key, iv => $self->{internal_crypt}->{iv} }) == 0) {
                return ($identity, $frame);
            }
        }

        $self->{logger}->writeLogError("[core] decrypt issue ($id): " .  $frame->getLastError());
        return undef;
    }

    return ($identity, $frame);
}

sub send_internal_response {
    my ($self, %options) = @_;

    my $response_type = defined($options{response_type}) ? $options{response_type} : 'ACK';
    my $data = gorgone::standard::library::json_encode(data => { code => $options{code}, data => $options{data} });
    # We add 'target' for 'PONG', 'SYNCLOGS'. Like that 'gorgone-proxy can get it
    my $message = '[' . $response_type . '] [' . (defined($options{token}) ? $options{token} : '') . '] ' . ($response_type =~ /^PONG|SYNCLOGS$/ ? '[] ' : '') . $data;

    if ($self->{internal_crypt}->{enabled} == 1) {
        try {
            $message = $self->{cipher}->encrypt(
                $message,
                $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_core_key},
                $self->{internal_crypt}->{iv}
            );
        } catch {
            $self->{logger}->writeLogError("[core] encrypt issue: $_");
            return undef;
        };
    }

    $self->{internal_socket}->send(pack('H*', $options{identity}), ZMQ_DONTWAIT | ZMQ_SNDMORE);
    $self->{internal_socket}->send($message, ZMQ_DONTWAIT);
    $self->router_internal_event();
}

sub send_internal_message {
    my ($self, %options) = @_;

    my $message = $options{message};
    if (!defined($message)) {
        $message = gorgone::standard::library::build_protocol(%options);
    }

    if ($self->{internal_crypt}->{enabled} == 1) {
        try {
            $message = $self->{cipher}->encrypt(
                $message,
                $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_core_key},
                $self->{internal_crypt}->{iv}
            );
        } catch {
            $self->{logger}->writeLogError("[core] encrypt issue: $_");
            return undef;
        };
    }

    $self->{internal_socket}->send($options{identity}, ZMQ_DONTWAIT | ZMQ_SNDMORE);
    $self->{internal_socket}->send($message, ZMQ_DONTWAIT);
    $self->router_internal_event();
}

sub broadcast_run {
    my ($self, %options) = @_;

    my $data = $options{frame}->decodeData();
    return if (!defined($data));

    if ($options{action} eq 'BCASTLOGGER') {
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
            gorgone => $self,
            dbh => $self->{db_gorgone},
            action => $options{action},
            logger => $self->{logger},
            frame => $options{frame},
            data => $options{data},
            token => $options{token}
        );
    }

    if ($options{action} eq 'BCASTCOREKEY') {
        $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_core_key_ctime} = time();
        $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_core_oldkey} = $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_core_key};
        $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_core_key} = pack('H*', $data->{key});
    }
}

sub message_run {
    my ($self, $options) = (shift, shift);

    if ($self->{logger}->is_debug()) {
        my $frame_ref = $options->{frame}->getFrame();
        $self->{logger}->writeLogDebug('[core] Message received - ' . $$frame_ref);
    }
    if ($options->{frame}->parse({ releaseFrame => 1 }) != 0) {
        return (undef, 1, { message => 'request not well formatted' });
    }
    my ($action, $token, $target) = ($options->{frame}->getAction(), $options->{frame}->getToken(), $options->{frame}->getTarget());

    # Check if not myself ;)
    if (defined($target) && ($target eq '' || (defined($self->{id}) && $target eq $self->{id}))) {
        $target = undef;
    }

    if (!defined($token) || $token eq '') {
        $token = gorgone::standard::library::generate_token();
    }

    if ($action !~ /^(?:ADDLISTENER|PUTLOG|GETLOG|KILL|PING|CONSTATUS|SETCOREID|SETMODULEKEY|SYNCLOGS|LOADMODULE|UNLOADMODULE|INFORMATION|GETTHUMBPRINT|BCAST.*)$/ && 
        !defined($target) && !defined($self->{modules_events}->{$action})) {
        gorgone::standard::library::add_history({
            dbh => $self->{db_gorgone},
            code => GORGONE_ACTION_FINISH_KO,
            token => $token,
            data => { error => "unknown_action", message => "action '$action' is unknown" },
            json_encode => 1
        });
        return (undef, 1, { error => "unknown_action", message => "action '$action' is unknown" });
    }

    $self->{counters}->{ $options->{router_type} }->{lc($action)} = 0 if (!defined($self->{counters}->{ $options->{router_type} }->{lc($action)}));
    $self->{counters}->{ $options->{router_type} }->{lc($action)}++;
    $self->{counters}->{total}++;
    $self->{counters}->{ $options->{router_type} }->{total}++;

    if ($self->{stop} == 1) {
        gorgone::standard::library::add_history({
            dbh => $self->{db_gorgone},
            code => GORGONE_ACTION_FINISH_KO,
            token => $token,
            data => { message => 'gorgone is stopping/restarting. Cannot proceed request.' },
            json_encode => 1
        });
        return ($token, 1, { message => 'gorgone is stopping/restarting. Cannot proceed request.' });
    }
    
    # Check Routing
    if (defined($target)) {
        if (!defined($self->{modules_id}->{ $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{proxy_name} }) || 
            !defined($self->{modules_register}->{ $self->{modules_id}->{ $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{proxy_name} } })) {
            gorgone::standard::library::add_history({
                dbh => $self->{db_gorgone},
                code => GORGONE_ACTION_FINISH_KO,
                token => $token,
                data => { error => "no_proxy", message => 'no proxy configured. cannot manage target.' },
                json_encode => 1
            });
            return ($token, 1, { error => "no_proxy", message => 'no proxy configured. cannot manage target.' });
        }

        $self->{counters}->{proxy}->{lc($action)} = 0 if (!defined($self->{counters}->{proxy}->{lc($action)}));
        $self->{counters}->{proxy}->{lc($action)}++;
        $self->{counters}->{proxy}->{total}++;

        $self->{modules_register}->{ $self->{modules_id}->{ $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{proxy_name} } }->{routing}->(
            gorgone => $self,
            dbh => $self->{db_gorgone},
            logger => $self->{logger}, 
            action => $action,
            token => $token,
            target => $target,
            frame => $options->{frame},
            hostname => $self->{hostname}
        );
        return ($token, 0);
    }
    
    if ($action =~ /^(?:ADDLISTENER|PUTLOG|GETLOG|KILL|PING|CONSTATUS|SETCOREID|SETMODULEKEY|SYNCLOGS|LOADMODULE|UNLOADMODULE|INFORMATION|GETTHUMBPRINT)$/) {
        my ($code, $response, $response_type) = $self->{internal_register}->{lc($action)}->(
            gorgone => $self,
            gorgone_config => $self->{config}->{configuration}->{gorgone},
            identity => $options->{identity},
            router_type => $options->{router_type},
            id => $self->{id},
            frame => $options->{frame},
            token => $token,
            logger => $self->{logger}
        );

        if ($action =~ /^(?:CONSTATUS|INFORMATION|GETTHUMBPRINT)$/) {
            gorgone::standard::library::add_history({
                dbh => $self->{db_gorgone},
                code => $code,
                token => $token,
                data => $response,
                json_encode => 1
            });
        }
        
        return ($token, $code, $response, $response_type);
    } elsif ($action =~ /^BCAST(.*)$/) {
        return (undef, 1, { message => "action '$action' is not known" }) if ($1 !~ /^(?:LOGGER|COREKEY)$/);
        $self->broadcast_run(
            action => $action,
            frame => $options->{frame},
            token => $token
        );
    } else {
        $self->{modules_register}->{ $self->{modules_events}->{$action}->{module}->{package} }->{routing}->(
            gorgone => $self,
            dbh => $self->{db_gorgone},
            logger => $self->{logger},
            action => $action,
            token => $token,
            target => $target,
            frame => $options->{frame},
            hostname => $self->{hostname}
        );
    }

    return ($token, 0);
}

sub router_internal_event {
    my ($self, %options) = @_;

    while (my $events = gorgone::standard::library::zmq_events(socket => $self->{internal_socket})) {
        if ($events & ZMQ_POLLIN) {
            my ($identity, $frame) = $self->read_internal_message();
            next if (!defined($identity));

            my ($token, $code, $response, $response_type) = $self->message_run(
                {
                    frame => $frame,
                    identity => $identity,
                    router_type => 'internal'
                }
            );
            $self->send_internal_response(
                identity => $identity,
                response_type => $response_type,
                data => $response,
                code => $code,
                token => $token
            );
        } else {
            last;
        }
    }
}

sub is_handshake_done {
    my ($self, %options) = @_;

    if (defined($self->{identity_infos}->{ $options{identity} })) {
        return (1, $self->{identity_infos}->{ $options{identity} });
    }

    return 0;
}

sub check_external_rotate_keys {
    my ($self, %options) = @_;

    my $time = time();
    my ($rv, $key, $iv);
    foreach my $id (keys %{$self->{identity_infos}}) {
        if ($self->{identity_infos}->{$id}->{mtime} < ($time - 86400)) {
            $self->{logger}->writeLogDebug('[core] clean external key for ' . $id);
            delete $self->{identity_infos}->{$id};
            next;
        }
        next if ($self->{identity_infos}->{$id}->{ctime} > ($time - $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{external_com_rotation}));

        $self->{logger}->writeLogDebug('[core] rotate external key for ' . $id);

        ($rv, $key) = gorgone::standard::library::generate_symkey(
            keysize => $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{external_com_keysize}
        );
        ($rv, $iv) = gorgone::standard::library::generate_symkey(keysize => 16);
        $rv = gorgone::standard::library::update_identity_attrs(
            dbh => $self->{db_gorgone},
            identity => $id,
            ctime => $time,
            oldkey => unpack('H*', $self->{identity_infos}->{$id}->{key}),
            oldiv => unpack('H*', $self->{identity_infos}->{$id}->{iv}),
            key => unpack('H*', $key),
            iv => unpack('H*', $iv)
        );
        next if ($rv == -1);

        my $message = gorgone::standard::library::json_encode(
            data => {
                hostname => $self->{hostname},
                cipher => $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{external_com_cipher},
                padding => $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{external_com_padding},
                key => unpack('H*', $key),
                iv => unpack('H*', $iv)
            }
        );

        $self->external_core_response(
            message => '[KEY] ' . $message,
            identity => $id,
            cipher_infos => {
                key => $self->{identity_infos}->{$id}->{key},
                iv => $self->{identity_infos}->{$id}->{iv}
            }
        );

        $self->{identity_infos}->{$id}->{ctime} = $time;
        $self->{identity_infos}->{$id}->{oldkey} = $self->{identity_infos}->{$id}->{key};
        $self->{identity_infos}->{$id}->{oldiv} = $self->{identity_infos}->{$id}->{iv};
        $self->{identity_infos}->{$id}->{key} = $key;
        $self->{identity_infos}->{$id}->{iv} = $iv;
    }
}

sub external_decrypt_message {
    my ($self, %options) = @_;

    my $message = $options{frame}->getFrame();

    my $crypt = MIME::Base64::decode_base64($$message);

    my $keys = [ { key => $options{cipher_infos}->{key}, iv => $options{cipher_infos}->{iv} } ];
    if (defined($options{cipher_infos}->{oldkey})) {
        push @$keys, { key => $options{cipher_infos}->{oldkey}, iv => $options{cipher_infos}->{oldiv} }
    }
    foreach my $key (@$keys) {
        my $plaintext;
        try {
            $plaintext = $self->{external_crypt_mode}->decrypt($crypt, $key->{key}, $key->{iv});
        };
        if (defined($plaintext) && $plaintext =~ /^\[[A-Za-z0-9_\-]+?\]/) {
            $options{frame}->setFrame(\$plaintext);
            return 0;
        }
    }

    $self->{logger}->writeLogError("[core] external decrypt issue: " .  ($_ ? $_ : 'no message'));
    return -1;
}

sub external_core_response {
    my ($self, %options) = @_;

    my $message = $options{message};
    if (!defined($message)) {
        my $response_type = defined($options{response_type}) ? $options{response_type} : 'ACK';
        my $data = gorgone::standard::library::json_encode(data => { code => $options{code}, data => $options{data} });
        # We add 'target' for 'PONG', 'SYNCLOGS'. Like that 'gorgone-proxy can get it
        $message = '[' . $response_type . '] [' . (defined($options{token}) ? $options{token} : '') . '] ' . ($response_type =~ /^PONG|SYNCLOGS$/ ? '[] ' : '') . $data;
    }

    if (defined($options{cipher_infos})) {
        try {
            $message = $self->{external_crypt_mode}->encrypt(
                $message,
                $options{cipher_infos}->{key},
                $options{cipher_infos}->{iv}
            );
        } catch {
            $self->{logger}->writeLogError("[core] external_core_response encrypt issue: $_");
            return undef;
        };

        $message = MIME::Base64::encode_base64($message, '');
    }

    $self->{external_socket}->send(pack('H*', $options{identity}), ZMQ_DONTWAIT|ZMQ_SNDMORE);
    $self->{external_socket}->send($message, ZMQ_DONTWAIT);
    $self->router_external_event();
}

sub external_core_key_response {
    my ($self, %options) = @_;

    my $data = gorgone::standard::library::json_encode(
        data => {
            hostname => $self->{hostname},
            cipher => $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{external_com_cipher},
            padding => $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{external_com_padding},
            key => unpack('H*', $options{key}),
            iv => unpack('H*', $options{iv})
        }
    );
    return -1 if (!defined($data));

    my $crypttext;
    try {
        $crypttext = $options{client_pubkey}->encrypt("[KEY] " . $data, 'v1.5');
    } catch {
        $self->{logger}->writeLogError("[core] core key response encrypt issue: $_");
        return -1;
    };

    $self->{external_socket}->send(pack('H*', $options{identity}), ZMQ_DONTWAIT | ZMQ_SNDMORE);
    $self->{external_socket}->send(MIME::Base64::encode_base64($crypttext, ''), ZMQ_DONTWAIT);
    $self->router_external_event();
    return 0;
}

sub handshake {
    my ($self, %options) = @_;

    my ($rv, $cipher_infos);
    my $first_message = $options{frame}->getFrame();

    # Test if it asks for the pubkey
    if ($$first_message =~ /^\s*\[GETPUBKEY\]/) {
        gorgone::standard::library::zmq_core_pubkey_response(
            socket => $self->{external_socket},
            identity => $options{identity},
            pubkey => $self->{server_pubkey}
        );
        $self->router_external_event();
        return 1;
    }

    ($rv, $cipher_infos) = $self->is_handshake_done(identity => $options{identity});

    if ($rv == 1) {
        my $response;

        ($rv) = $self->external_decrypt_message(
            frame => $options{frame},
            cipher_infos => $cipher_infos
        );

        my $message = $options{frame}->getFrame();
        if ($rv == 0 && $$message =~ /^(?:[\[a-zA-Z-_]+?\]\s+\[.*?\]|[\[a-zA-Z-_]+?\]\s*$)/) {
            gorgone::standard::library::update_identity_mtime(dbh => $self->{db_gorgone}, identity => $options{identity});
            return (0, $cipher_infos);
        }

        # Maybe he want to redo a handshake
        $rv = 0;    
    }

    if ($rv == 0) {
        my ($client_pubkey, $key, $iv);

        # We try to uncrypt
        ($rv, $client_pubkey) = gorgone::standard::library::is_client_can_connect(
            privkey => $self->{server_privkey},
            message => $$first_message,
            logger => $self->{logger},
            authorized_clients => $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{authorized_clients}
        );
        if ($rv == -1) {
            $self->external_core_response(
                identity => $options{identity},
                code => GORGONE_ACTION_FINISH_KO,
                data => { message => 'handshake issue' }
            );
            return -1;
        }
        ($rv, $key) = gorgone::standard::library::generate_symkey(
            keysize => $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{external_com_keysize}
        );
        ($rv, $iv) = gorgone::standard::library::generate_symkey(keysize => 16);

        if (gorgone::standard::library::add_identity(dbh => $self->{db_gorgone}, identity => $options{identity}, key => $key, iv => $iv) == -1) {
            $self->external_core_response(
                identity => $options{identity},
                code => GORGONE_ACTION_FINISH_KO,
                data => { message => 'handshake issue' }
            );
        }

        $self->{identity_infos}->{ $options{identity} } = {
            ctime => time(),
            mtime => time(),
            key => $key,
            oldkey => undef,
            iv => $iv,
            oldiv => undef
        };

        $rv = $self->external_core_key_response(
            identity => $options{identity},
            client_pubkey => $client_pubkey,
            key => $key,
            iv => $iv
        );
        if ($rv == -1) {
            $self->external_core_response(
                identity => $options{identity},
                code => GORGONE_ACTION_FINISH_KO,
                data => { message => 'handshake issue' }
            );
        }
    }

    return -1;
}

sub send_message_parent {
    my (%options) = @_;

    if ($options{router_type} eq 'internal') {
        $gorgone->send_internal_response(
            identity => $options{identity},
            response_type => $options{response_type},
            data => $options{data},
            code => $options{code},
            token => $options{token}
        );
    }
    if ($options{router_type} eq 'external') {
        my ($rv, $cipher_infos) = $gorgone->is_handshake_done(identity => $options{identity});
        return if ($rv == 0);
        $gorgone->external_core_response(
            cipher_infos => $cipher_infos,
            identity => $options{identity},
            response_type => $options{response_type},
            token => $options{token},
            code => $options{code},
            data => $options{data}
        );
    }
}

sub router_external_event {
    my ($self, %options) = @_;

    while (my $events = gorgone::standard::library::zmq_events(socket => $self->{external_socket})) {
        if ($events & ZMQ_POLLIN) {
            my ($identity, $frame) = gorgone::standard::library::zmq_read_message(
                socket => $self->{external_socket},
                logger => $self->{logger}
            );
            next if (!defined($identity));

            my ($rv, $cipher_infos) = $self->handshake(
                identity => $identity,
                frame => $frame
            );
            if ($rv == 0) {
                my ($token, $code, $response, $response_type) = $self->message_run(
                    {
                        frame => $frame,
                        identity => $identity,
                        router_type => 'external'
                    }
                );
                $self->external_core_response(
                    identity => $identity,
                    cipher_infos => $cipher_infos,
                    response_type => $response_type,
                    token => $token, 
                    code => $code,
                    data => $response
                );
            }
        } else {
            last;
        }
    }
}

sub waiting_ready_pool {
    my (%options) = @_;

    my $name = $gorgone->{modules_id}->{$gorgone->{config}->{configuration}->{gorgone}->{gorgonecore}->{proxy_name}};
    my $method = $name->can('is_all_proxy_ready');

    if ($method->() > 0) {
        return 1;
    }

    my $watcher_timer = $gorgone->{loop}->timer(10, 0, \&stop_ev);
    $gorgone->{loop}->run();

    if ($method->() > 0) {
        return 1;
    }

    return 0;
}

sub stop_ev {
    $gorgone->{loop}->break();
    $gorgone->check_exit_modules();
}

sub waiting_ready {
    my (%options) = @_;

    return 1 if (${$options{ready}} == 1);

    my $watcher_timer = $gorgone->{loop}->timer(10, 0, \&stop_ev);
    $gorgone->{loop}->run();

    if (${$options{ready}} == 0) {
        return 0;
    }

    return 1;
}

sub quit {
    my ($self, %options) = @_;
    
    $self->{logger}->writeLogInfo("[core] Quit main process");

    if ($self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_type} eq 'ipc') {
        unlink($self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_path});
    }
    exit(0);
}

sub check_exit_modules {
    my ($self, %options) = @_;

    my $current_time = time();

    # check key rotate
    if ($self->{internal_crypt}->{enabled} == 1 &&
        ($current_time - $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_core_key_ctime}) > $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_rotation}) {
        $self->broadcast_core_key();
    }
    if (defined($self->{external_socket})) {
        $self->check_external_rotate_keys();
    }

    my $count = 0;
    if (time() - $self->{cb_timer_check} > 15 || $self->{stop} == 1) {
        if ($self->{stop} == 1 && (!defined($self->{sigterm_last_time}) || ($current_time - $self->{sigterm_last_time}) >= 10)) {
            $self->{sigterm_start_time} = time() if (!defined($self->{sigterm_start_time}));
            $self->{sigterm_last_time} = time();
            foreach my $name (keys %{$self->{modules_register}}) {
                $self->{modules_register}->{$name}->{gently}->(logger => $gorgone->{logger});
            }
        }

        foreach my $name (keys %{$self->{modules_register}}) {
            my ($count_module, $keepalive) = $self->{modules_register}->{$name}->{check}->(
                gorgone => $self,
                logger => $self->{logger},
                dead_childs => $self->{return_child},
                dbh => $self->{db_gorgone},
                api_endpoints => $self->{api_endpoints}
            );

            $count += $count_module;
            if ($count_module == 0 && (!defined($keepalive) || $keepalive == 0)) {
                $self->unload_module(package => $name);
            }
        }

        $self->{cb_timer_check} = time();
        # We can clean old return_child.
        foreach my $pid (keys %{$self->{return_child}}) {
            if (($self->{cb_timer_check} - $self->{return_child}->{$pid}) > 300) {
                delete $self->{return_child}->{$pid};
            }
        }
    }

    if ($self->{stop} == 1) {
        # No childs
        if ($count == 0) {
            $self->quit();
        }

        # Send KILL
        if (time() - $self->{sigterm_start_time} > $self->{config}->{configuration}->{gorgone}->{gorgonecore}->{timeout}) {
            foreach my $name (keys %{$self->{modules_register}}) {
                $self->{modules_register}->{$name}->{kill_internal}->(logger => $gorgone->{logger});
            }
            $self->quit();
        }
    }
}

sub periodic_exec {
    $gorgone->check_exit_modules();
    $gorgone->{listener}->check();
}

sub run {
    $gorgone = shift;

    $gorgone->SUPER::run();
    $gorgone->{logger}->redirect_output();

    $gorgone->{logger}->writeLogInfo("[core] Gorgoned started");
    $gorgone->{logger}->writeLogInfo("[core] PID $$");

    if (gorgone::standard::library::add_history({
        dbh => $gorgone->{db_gorgone},
        code => GORGONE_STARTED,
        data => { message => 'gorgoned is starting...' },
        json_encode => 1}) == -1
    ) {
        $gorgone->{logger}->writeLogInfo("[core] Cannot write in history. We quit!!");
        exit(1);
    }

    {
        local $SIG{__DIE__};
        $gorgone->{zmq_context} = ZMQ::FFI->new();
    }

    $gorgone->{internal_socket} = gorgone::standard::library::create_com(
        context => $gorgone->{zmq_context},
        type => $gorgone->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_type},
        path => $gorgone->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_path},
        zmq_type => 'ZMQ_ROUTER',
        name => 'router-internal',
        zmq_router_handover => $gorgone->{config}->{configuration}->{gorgone}->{gorgonecore}->{internal_com_zmq_router_handover},
        logger => $gorgone->{logger}
    );

    if (defined($gorgone->{config}->{configuration}->{gorgone}->{gorgonecore}->{external_com_type}) && $gorgone->{config}->{configuration}->{gorgone}->{gorgonecore}->{external_com_type} ne '') {
        if ($gorgone->{keys_loaded}) {
            $gorgone->init_external_informations();

            $gorgone->{external_socket} = gorgone::standard::library::create_com(
                context => $gorgone->{zmq_context},
                type => $gorgone->{config}->{configuration}->{gorgone}->{gorgonecore}->{external_com_type},
                path => $gorgone->{config}->{configuration}->{gorgone}->{gorgonecore}->{external_com_path},
                zmq_type => 'ZMQ_ROUTER',
                zmq_router_handover => $gorgone->{config}->{configuration}->{gorgone}->{gorgonecore}->{external_com_zmq_router_handover},
                zmq_tcp_keepalive => $gorgone->{config}->{configuration}->{gorgone}->{gorgonecore}->{external_com_zmq_tcp_keepalive},
                zmq_ipv6 => $gorgone->{config}->{configuration}->{gorgone}->{gorgonecore}->{ipv6},
                name => 'router-external',
                logger => $gorgone->{logger}
            );
        } else {
            $gorgone->{logger}->writeLogError("[core] Cannot create external com: no keys loaded");
        }
    }

    # init all modules
    foreach my $name (keys %{$gorgone->{modules_register}}) {
        $gorgone->{logger}->writeLogDebug("[core] Call init function from module '$name'");
        $gorgone->{modules_register}->{$name}->{init}->(
            gorgone => $gorgone,
            id => $gorgone->{id},
            logger => $gorgone->{logger},
            poll => $gorgone->{poll},
            external_socket => $gorgone->{external_socket},
            internal_socket => $gorgone->{internal_socket},
            dbh => $gorgone->{db_gorgone},
            api_endpoints => $gorgone->{api_endpoints}
        );
    }

    $gorgone->{listener} = gorgone::class::listener->new(
        gorgone => $gorgone,
        logger => $gorgone->{logger}
    );
    $gorgone::standard::library::listener = $gorgone->{listener};

    $gorgone->{logger}->writeLogInfo("[core] Server accepting clients");
    $gorgone->{cb_timer_check} = time();

    $gorgone->{loop} = new EV::Loop();
    $gorgone->{watcher_timer} = $gorgone->{loop}->timer(5, 5, \&periodic_exec);
    $gorgone->{watcher_io_internal} = $gorgone->{loop}->io($gorgone->{internal_socket}->get_fd(), EV::READ, sub { $gorgone->router_internal_event() });
    if (defined($gorgone->{external_socket})) {
        $gorgone->{watcher_io_external} = $gorgone->{loop}->io($gorgone->{external_socket}->get_fd(), EV::READ, sub { $gorgone->router_external_event() });
    }

    $gorgone->{loop}->run();
}

1;

__END__
