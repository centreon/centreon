#!/usr/bin/perl
# Copyright 2026 Centreon (http://www.centreon.com/)
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
use YAML::XS;
use File::Copy;
# migration tool to enable gorgone websocket server, exit = 0 mean ok (either the change was made or not needed), exit = 1 mean an error.
my $apache_conf = '/etc/apache2/';
my $conf_file = $ENV{"GORGONE_CONF_FILE"} // '/etc/centreon-gorgone/config.d/40-gorgoned.yaml';

sub main {
    my $mode = shift @ARGV;
    my $os = shift @ARGV;
    if (!defined($mode) or $mode ne 'websocket') {
        print("unimplemented mode: $mode\n");
        exit 0;
    }
    $apache_conf = "/etc/httpd/conf.d/" if $os eq 'rpm';
    # Check if we need to update the gorgone configuration file, in an idempotent way.
    # we expect the standard conf placement, if the file is not there, we consider it's a new installation, and let the normal process create the file with the correct conf.
    exit 0 if (!-f $conf_file);
    my $config;
    my @conf_string;

    eval {
        $config = YAML::XS::LoadFile($conf_file);
        $config = $config->{gorgone};
        open my $fh, '<', $conf_file or die "Could not open file '$conf_file' $!";
        @conf_string = <$fh>;
    };
    if ($@) {
        print("config - yaml load file '$conf_file' error: $@\n");
        exit 1;
    }

    if (!defined($config->{modules}) or ref($config->{modules}) ne 'ARRAY') {
        print("no modules array in config file '$conf_file', you should check your gorgone installation\n");
        exit 1;
    }
    for my $module (@{$config->{modules}}) {
        # module are stored in an array, if the proxy module don't exist the configuration is not standard, we don't change anything.
        next if !defined($module->{name}) or $module->{name} ne 'proxy';
        gorgone_add_http_conf(\@conf_string);
        print("validating the gorgone conf file, make a backup, and set it as new conf.\n\n");
        exit(1) if !validate_and_install_file(old => $config, new => \@conf_string);

        if (defined($module->{httpserver})
            and defined($module->{httpserver}->{enable})
            and $module->{httpserver}->{enable} =~ "true|1") {

            if ($module->{httpserver}->{ssl} =~ "true|1") {
                # tls enabled : apache need to listen with tls.
                link_apache_conf(1);
            }
        }
        else {
            # tls is not active but gorgone was listening on 8086, apache must step in.
            link_apache_conf(0);
        }

    }
    print("finished gorgone migration.\n");
}
# check the configuration is valid, and install it.
# return 0 on success and 1 on failure.
sub validate_and_install_file {
    my %options = @_;

    my $new_conf_string = join("", @{$options{new}});
    my $new_conf_obj;
    eval {
    $new_conf_obj = YAML::XS::Load($new_conf_string);
    };
    if ($@) {
        print("generated configuration is not valid yaml, the migration failed, see debug output.\n");
        dbg("yaml could not be read : " . $new_conf_string);
        return 1;
    }

    if (!copy( $conf_file, $conf_file . ".back-" . time() ) ) {
        print("Backup copy failed: $!\n");
        return 1;
    }
    my $fh;
    if (!open $fh, '>', $conf_file ) {
        print("Could not open file '$conf_file' $!\n");
        return 1;
    }
    print($fh $new_conf_string);
    close($fh);
}

sub dbg {
    print(@_) if $ENV{PERL_DEBUG},
}
sub gorgone_add_http_conf {
    my $conf_string = shift;
    my $start_line;
    my $end_line;
    my $proxy_line_nb;

    my $httpserver_configured = 0;
    my $enable_configured = 0;
    my $ssl_configured = 0;
    my $address_configured = 0;
    my $port_configured = 0;

    my $address_line;
    my $proxy_line;
    my $space;

    for (my $i = 0; $i < @$conf_string; $i++) {
        if ($$conf_string[$i] =~ /^\s*- name:\s*proxy/) {
            $proxy_line_nb = $i;
            $proxy_line = $$conf_string[$i];
            next;
        }
        next if !$proxy_line_nb;
        if ($$conf_string[$i] =~ /^\s*httpserver:\s*/) {
            $start_line = $i;
            print("found an httpserver line.");
            $httpserver_configured = 1;
        }
        next if !defined($start_line);
        if ($$conf_string[$i] =~ /^\s*enable:(.*)/) {
            $$conf_string[$i] =~ s/enable:(.*)/enable: true/;
            $enable_configured = 1;
        }
        if ($$conf_string[$i] =~ /^\s*address:(.*)/) {
            $address_line = $$conf_string[$i];
            $$conf_string[$i] =~ s/address:(.*)/address: "127.0.0.1"/;
            $address_configured = 1;
        }

        if ($$conf_string[$i] =~ /^\s*port:(.*)/) {
            $$conf_string[$i] =~ s/port:(.*)/port: 8087/;
            $port_configured = 1;
        }
        if ($$conf_string[$i] =~ /^\s*ssl:(.*)/) {
            $$conf_string[$i] =~ s/ssl:(.*)/ssl: false/;
            $ssl_configured = 1;
        }
        if ($$conf_string[$i] =~ /^\s*- name:/) {
            $end_line = $i;
            last;
        }
    }
    if ($httpserver_configured == 1 and $address_configured == 1 and $ssl_configured == 1 and $port_configured == 1) {
        # configuration seem to have been correctly applied
        return;
    }
    $proxy_line =~ /^(\s*)/g;
    $space = $1 . "  ";
    my $line_nb;
    # now we manage the case where nothing or only some parameters are configured.
    # we need to know which line we need to insert the new configuration, and will insert any missing line.
    if (defined($start_line)) {
        $line_nb = $start_line;
    }
    elsif (defined($proxy_line_nb)) {
        $line_nb = $proxy_line_nb;
    }

    else {
        print("No proxy configuration found in the configuration file. The migration of gorgone to pullwss(websocket) can not be automatic. Please migrate your configuration manually by enabling the proxy module and the httpserver submodule in $conf_file\n");
        exit 1;
    }
    $line_nb++;

    # we have a start line, we can push lines in the middle of the array
    if ($httpserver_configured == 0) {
        splice(@$conf_string, $line_nb, 0, ($space . "httpserver:\n"));
        $line_nb++;
    }
    $space = $space . "  ";
    for my $case
    ({ test => $enable_configured, val => "enable: true"},
    {test => $ssl_configured, val =>  "ssl: false"},
    {test => $address_configured, val =>  'address: "127.0.0.1"'},
    {test => $port_configured, val => "port: 8087"}) {

        next if !$case->{test} == 0;
        splice(@$conf_string, $line_nb, 0, ($space . $case->{val} . "\n"));
        $line_nb++;
    }
}

sub link_apache_conf {
    my ($is_tls) = @_;
    if (system("apache2ctl configtest") != 0) {
        print("Failed to check configuration before any change. Make sure your apache2 configuration is valide before making the migration.");
        return 1;
    }

    my $file;
    if ($is_tls) {
        $file = "centreon-apache-https-gorgone.conf";
    }
    else {
        $file = "centreon-apache-gorgone.conf";
    }
    my $source = $apache_conf . "sites-available/" . $file;
    my $dst = $apache_conf . "sites-enabled/centreon-apache-gorgone.conf";
    if (-f $dst) {
        print("file already exist, maybe we are not the first run ? \n");
        return 1;
    }
    if (!-f $source) {
        print("no file $source found, no modification will be done to apache configuration.");
        return 1;
    }
    print("linking a file in apache conf.\n");
    my $rc = symlink($source, $dst);
    if ($rc != 1) {
        print("error creating the symlink, apache configuration will not be valid\n ");
        return 1;
    }
    print("Apache symlink created.\n");
    if (system("apache2ctl configtest") != 0) {
        print("Failed to check configuration, apache was in an invalid state after our modification. Maybe the tls certs are not in the expected placement ? you can manually create a file in $dst akin to $source to set correctly your tls certificates.");
        unlink($dst);
        return 1;
    }
    print("Apache configuration seem valid.\n");
    return 0;
}

main();