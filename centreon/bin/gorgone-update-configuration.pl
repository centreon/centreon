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
# migration tool to enable gorgone websocket server, exit = 0 mean ok (either the change was made or not needed), exit = 1 mean an error.
my $apache_conf = '/etc/apache2/';
my $conf_file = '/etc/centreon-gorgone/config.d/40-gorgoned.yaml';

sub main {
    my $mode = shift @ARGV;
    my $os = shift @ARGV;
    if (!defined($mode) or $mode ne 'websocket') {
        print("unimplemented mode: $mode\n");
        exit 1;
    }
    $apache_conf = "/etc/httpd/conf.d/" if $os eq 'rpm';
    # now let's check if we need to update the gorgone configuration file, in an idempotent way.
    # we expect the standard conf placement, if the file is not there, we consider it's a new installation, and let the normal process create the file with the correct conf.
    exit 0 if (!-f $conf_file);
    my $config;
    my $string;
    my @conf_string;

    eval {
        $config = YAML::XS::LoadFile($conf_file);
        $string = YAML::XS::Dump($config->{modules});
        $config = $config->{gorgone};
        open my $fh, '<', $conf_file or die "Could not open file '$conf_file' $!";
        @conf_string = <$fh>;
    };
    if ($@) {
        print("config - yaml load file '$conf_file' error: $@\n");
        exit 0;
    }

    if (!defined($config->{modules}) or ref($config->{modules}) ne 'ARRAY') {
        print("no modules array in config file '$conf_file', you should check your gorgone installation\n");
        exit 1;
    }
    for my $module (@{$config->{modules}}) {
        next if !defined($module->{name}) or $module->{name} ne 'proxy';
        print("Found a module proxy.");
        # cas 1 : actif avec tls
        # cas 2 : tous les autres cas : on ecrase avec notre conf http sur 8087
        if ( defined($module->{httpserver})
            and defined($module->{httpserver}->{enable})
            and $module->{httpserver}->{enable} =~ "true|1") {

            if ($module->{httpserver}->{ssl} =~ "true|1") {
                    # tls enabled : we now it's the first run as we disable it after.
                    gorgone_set_localhost_address(\@conf_string);
                    print("NEW FILE : \n\n");
                    print(join("", @conf_string));
                    link_apache_conf(1);
            }else { # cas websocket déjà actif mais pas tls, si on écoute que sur localhost, on ne fait rien.
                if (defined($module->{httpserver}->{address}) and $module->{httpserver}->{address} !~ /^127.0.0.1|localhost|::1$/i){
                    gorgone_set_localhost_address(\@conf_string);
                    print("NEW FILE : \n\n");
                    print(join("", @conf_string));
                    link_apache_conf(0);
                }
            }
            exit(0);
        }else {
        # httpserver is not enabled, we can't know if other value are present, so we need to be extra careful while migrating, if enable:false and address:localhost for exemple.
            gorgone_add_http_conf(\@conf_string);
            print("NEW FILE : \n\n");
            print(join("", @conf_string));
        }
    }

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
        if ($$conf_string[$i] =~ /httpserver/) {
            print("stop : \n" . $$conf_string[$i] . "\n\n");
        }
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
            $$conf_string[$i] =~ s/address:(.*)/address: "localhost"/;
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
    print("finished replace\n");
    if ($httpserver_configured == 1 and $address_configured == 1 and $ssl_configured == 1 and $port_configured == 1 ){
        # configuration seem to have been correctly applied
        return;
    }
    $proxy_line =~ /^(\s*)/g;
    $space = $1 . "  ";
    my $line_nb;
    # now we manage the case where nothing or only some parameters are configured.
    # we need to know which line we need to insert the new configuration.
    if (defined($start_line)){
        $line_nb = $start_line;
    }elsif (defined($proxy_line_nb)) {
        $line_nb = $proxy_line_nb ;
    }elsif (defined($end_line)){
        $line_nb = $end_line;
    }else {
        $line_nb = scalar(@$conf_string);
    }
    $line_nb++;

    # we have a start line, we can simply push line in the middle of the array
    if ($httpserver_configured == 0){
        splice(@$conf_string, $line_nb, 0, ($space . "httpserver:\n"));
    }
    $space = $space. "  ";
    for my ($cond, $value)
    (($enable_configured, "enable: true"),
    ($ssl_configured,     "ssl: false"),
    ($address_configured, 'address: "localhost"'),
    ($port_configured,    "port: 8087"))
    {
        next if !$cond == 0;
        splice(@$conf_string, $line_nb, 0, ($space .$value . "\n"));
        $line_nb++;
    }
}
sub gorgone_set_localhost_address {
    # migrate listener to localhost 8087 if conf is already enabled.
    my $conf_string = shift;
    my $start_line;
    my $end_line;
    my $in_proxy = 0;
    for (my $i = 0; $i < @$conf_string; $i++) {

        if ($$conf_string[$i] =~ /- name:\s*proxy/) {
            $in_proxy = 1;
            next;
        }
        next if $in_proxy == 0;
        if ($$conf_string[$i] =~ /\s*httpserver:\s*$/) {
            $start_line = $i;
        }
        next if !defined($start_line);
        if ($$conf_string[$i] =~ /\s*address:(.*)/) {
            $$conf_string[$i] =~ s/address:(.*)/address: "localhost"/;
        }
        if ($$conf_string[$i] =~ /\s*port:(.*)/) {
            $$conf_string[$i] =~ s/port:(.*)/port: 8087/;
        }
        if ($$conf_string[$i] =~ /\s*ssl:(.*)/) {
            $$conf_string[$i] =~ s/ssl:(.*)/ssl: false/;
        }
        if ($$conf_string[$i] =~ /\s*- name:/) {
            $$end_line = $i;
            last;
        }
    }

}
sub validate_file {
    print("TOOD\n");
}
sub link_apache_conf {
    my ( $is_tls) = @_;

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
        print("file already exist, maybe we are not the first run ? ");
        exit(0);
    }
    print("linking a file in apache conf.");
    my $rc = symlink($source, $dst);
    if ($rc != 0) {
        print("error creating the symlink, apache configuration will not be valid, so we cancel the ")
    }




}
main;
