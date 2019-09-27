#!/usr/bin/perl

use warnings;
use strict;
use FindBin;
use lib "$FindBin::Bin";
# to be launched from contrib directory
use lib "$FindBin::Bin/../";

gorgone::script::gorgone_config_init->new()->run();

package gorgone::script::gorgone_config_init;

use strict;
use warnings;
use gorgone::standard::misc;

use base qw(gorgone::class::script);

use vars qw($centreon_config);

sub new {
    my $class = shift;
    my $self = $class->SUPER::new("gorgone_config_init",
        centreon_db_conn => 0,
        centstorage_db_conn => 0,
        noconfig => 0
    );

    bless $self, $class;
    $self->add_options(
        'centcore-config:s' => \$self->{centcore_config},
        'gorgone-config:s'  => \$self->{gorgone_config},
    );
    return $self;
}

sub init {
    my $self = shift;
    $self->SUPER::init();

    $self->{centcore_config} = '/etc/centreon/conf.pm' if (!defined($self->{centcore_config}) || $self->{centcore_config} eq '');
    $self->{gorgone_config} = '/etc/centreon/gorgoned.yml' if (!defined($self->{gorgone_config}) || $self->{gorgone_config} eq '');
}

sub read_centcore_config {
    my ($self) = @_;

    unless (my $return = do $self->{centcore_config}) {
        $self->{logger}->writeLogError("couldn't parse $self->{centcore_config}: $@") if $@;
        $self->{logger}->writeLogError("couldn't do $self->{centcore_config}: $!") unless defined $return;
        $self->{logger}->writeLogError("couldn't run $self->{centcore_config}") unless $return;
        exit(1);
    }

    if (!defined($centreon_config->{VarLib})) {
        $self->{logger}->writeLogError("config file doesn't look like a centcore config file");
        exit(1);
    }

    $centreon_config->{VarLib} =~ s/\/$//;
    if ($centreon_config->{db_host} =~ /^(.*?):(\d+)$/) {
        $centreon_config->{db_host} = $1;
        $centreon_config->{db_port} = $2;
    }
}

sub write_gorgone_config {
    my ($self) = @_;

    my $fh;
    if (!open($fh, '>', $self->{gorgone_config})) {
        $self->{logger}->writeLogError("couldn't open file '$self->{gorgone_config}': $!");
        exit(1);
    }

    my $db_port = '';
    if (defined($centreon_config->{db_port})) {
        $db_port = ';port=' . $centreon_config->{db_port};
    }

    my $content = <<"END_FILE";
name: gorgoned
description: Configuration init by gorgone_config_init
database:
  db_centreon:
    dsn: "mysql:host=$centreon_config->{db_host}${db_port};dbname=$centreon_config->{centreon_db}"
    username: "$centreon_config->{db_user}"
    password: "$centreon_config->{db_passwd}"
  db_centstorage:
    dsn: "mysql:host=$centreon_config->{db_host}${db_port};dbname=$centreon_config->{centstorage_db}"
    username: "$centreon_config->{db_user}"
    password: "$centreon_config->{db_passwd}"
gorgonecore:
  external_com_type: tcp
  external_com_path: "*:5555"
  hostname:
  id:
modules:
  - name: httpserver
    package: gorgone::modules::core::httpserver::hooks
    enable: false
    address: 0.0.0.0
    port: 8443
    ssl: true
    ssl_cert_file: /etc/pki/tls/certs/server-cert.pem
    ssl_key_file: /etc/pki/tls/server-key.pem
    auth:
      user: admin
      password: password

  - name: cron
    package: gorgone::modules::core::cron::hooks
    enable: false

  - name: action
    package: gorgone::modules::core::action::hooks
    enable: true

  - name: proxy
    package: gorgone::modules::core::proxy::hooks
    enable: true

  - name: pollers
    package: gorgone::modules::centreon::pollers::hooks
    enable: true

  - name: broker
    package: gorgone::modules::centreon::broker::hooks
    enable: false
    cache_dir: "/var/lib/centreon/broker-stats/"
    cron:
      - id: broker_stats
        timespec: "*/2 * * * *"
        action: BROKERSTATS
        parameters:
          timeout: 10

  - name: legacycmd
    package: gorgone::modules::centreon::legacycmd::hooks
    enable: true
    cmd_file: "$centreon_config->{VarLib}/centcore.cmd"
    cache_dir: "$centreon_config->{CacheDir}"
    cache_dir_trap: "/etc/snmp/centreon_traps/"
    remote_dir: "$centreon_config->{VarLib}/remote-data/"
END_FILE

    chomp $content;
    print $fh $content;
    close($fh);
}

sub run {
    my $self = shift;

    $self->SUPER::run();
    $self->read_centcore_config();
    $self->write_gorgone_config();

    $self->{logger}->writeLogInfo("file '$self->{gorgone_config}' created success");
}

__END__

=head1 NAME

gorgone_config_init.pl - script to create gorgone config to replace centcore

=head1 SYNOPSIS

gorgone_config_init.pl [options]

=head1 OPTIONS

=over 8

=item B<--centcore-config>

Specify the path to the centcore configuration file (default: '/etc/centreon/conf.pm').

=item B<--gorgone-config>

Specify the gorgone config file created (default: '/etc/centreon/gorgoned.yml').

=item B<--severity>

Set the script log severity (default: 'error').

=item B<--help>

Print a brief help message and exits.

=back

=head1 DESCRIPTION

B<gorgone_config_init.pl>

=cut

