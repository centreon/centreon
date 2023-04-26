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

package gorgone::standard::misc;

use strict;
use warnings;
use vars qw($centreon_config);
use POSIX ":sys_wait_h";
use File::Path;
use File::Basename;
use Try::Tiny;

sub reload_db_config {
    my ($logger, $config_file, $cdb, $csdb) = @_;
    my ($cdb_mod, $csdb_mod) = (0, 0);
    
    unless (my $return = do $config_file) {
        $logger->writeLogError("[core] Couldn't parse $config_file: $@") if $@;
        $logger->writeLogError("[core] Couldn't do $config_file: $!") unless defined $return;
        $logger->writeLogError("[core] Couldn't run $config_file") unless $return;
        return -1;
    }
    
    if (defined($cdb)) {
        if ($centreon_config->{centreon_db} ne $cdb->db() ||
            $centreon_config->{db_host} ne $cdb->host() ||
            $centreon_config->{db_user} ne $cdb->user() ||
            $centreon_config->{db_passwd} ne $cdb->password() ||
            $centreon_config->{db_port} ne $cdb->port()) {
            $logger->writeLogInfo("[core] Database centreon config has been modified");
            $cdb->db($centreon_config->{centreon_db});
            $cdb->host($centreon_config->{db_host});
            $cdb->user($centreon_config->{db_user});
            $cdb->password($centreon_config->{db_passwd});
            $cdb->port($centreon_config->{db_port});
            $cdb_mod = 1;
        }
    }
    
    if (defined($csdb)) {
        if ($centreon_config->{centstorage_db} ne $csdb->db() ||
            $centreon_config->{db_host} ne $csdb->host() ||
            $centreon_config->{db_user} ne $csdb->user() ||
            $centreon_config->{db_passwd} ne $csdb->password() ||
            $centreon_config->{db_port} ne $csdb->port()) {
            $logger->writeLogInfo("[core] Database centstorage config has been modified");
            $csdb->db($centreon_config->{centstorage_db});
            $csdb->host($centreon_config->{db_host});
            $csdb->user($centreon_config->{db_user});
            $csdb->password($centreon_config->{db_passwd});
            $csdb->port($centreon_config->{db_port});
            $csdb_mod = 1;
        }
    }
   
    return (0, $cdb_mod, $csdb_mod);
}

sub get_all_options_config {
    my ($extra_config, $centreon_db_centreon, $prefix) = @_;

    my $save_force = $centreon_db_centreon->force();
    $centreon_db_centreon->force(0);
    
    my ($status, $stmt) = $centreon_db_centreon->query({
        query => 'SELECT `key`, `value` FROM options WHERE `key` LIKE ? LIMIT 1',
        bind_values => [$prefix . '_%']
    });
    if ($status == -1) {
        $centreon_db_centreon->force($save_force);
        return ;
    }
    while ((my $data = $stmt->fetchrow_hashref())) {
        if (defined($data->{value}) && length($data->{value}) > 0) {
            $data->{key} =~ s/^${prefix}_//;
            $extra_config->{$data->{key}} = $data->{value};
        }
    }
    
    $centreon_db_centreon->force($save_force);
}

sub get_option_config {
    my ($extra_config, $centreon_db_centreon, $prefix, $key) = @_;
    my $data;
 
    my $save_force = $centreon_db_centreon->force();
    $centreon_db_centreon->force(0);
    
    my ($status, $stmt) = $centreon_db_centreon->query({
        query => 'SELECT value FROM options WHERE `key` = ? LIMIT 1',
        bind_values => [$prefix . '_' . $key]
    });
    if ($status == -1) {
        $centreon_db_centreon->force($save_force);
        return ;
    }
    if (($data = $stmt->fetchrow_hashref()) && defined($data->{value})) {
        $extra_config->{$key} = $data->{value};
    }
    
    $centreon_db_centreon->force($save_force);
}

sub check_debug {
    my ($logger, $key, $cdb, $name) = @_;
    
    my ($status, $sth) =  $cdb->query({
        query => 'SELECT `value` FROM options WHERE `key` = ?',
        bind_values => [$key]
    });
    return -1 if ($status == -1);
    my $data = $sth->fetchrow_hashref();
    if (defined($data->{'value'}) && $data->{'value'} == 1) {
        if (!$logger->is_debug()) {
            $logger->severity("debug");
            $logger->writeLogInfo("[core] Enable Debug in $name");
        }
    } else {
        if ($logger->is_debug()) {
            $logger->set_default_severity();
            $logger->writeLogInfo("[core] Disable Debug in $name");
        }
    }
    return 0;
}

sub backtick {
    my %arg = (
        command => undef,
        arguments => [],
        timeout => 30,
        wait_exit => 0,
        redirect_stderr => 0,
        @_,
    );
    my @output;
    my $pid;
    my $return_code;
    
    my $sig_do;
    if ($arg{wait_exit} == 0) {
        $sig_do = 'IGNORE';
        $return_code = undef;
    } else {
        $sig_do = 'DEFAULT';
    }
    local $SIG{CHLD} = $sig_do;
    $SIG{TTOU} = 'IGNORE';
    $| = 1;

    if (!defined($pid = open( KID, "-|" ))) {
        $arg{logger}->writeLogError("[core] Cant fork: $!");
        return (-1000, "cant fork: $!");
    }
    
    if ($pid) {
        try {
           local $SIG{ALRM} = sub { die "Timeout by signal ALARM\n"; };
           alarm( $arg{timeout} );
           while (<KID>) {
               chomp;
               push @output, $_;
           }

           alarm(0);
        } catch {
            if ($pid != -1) {
                kill -9, $pid;
            }

            alarm(0);
            return (-1000, "Command too long to execute (timeout)...", -1);
        };
        if ($arg{wait_exit} == 1) {
            # We're waiting the exit code                
            waitpid($pid, 0);
            $return_code = ($? >> 8);
        }
        close KID;
    } else {
        # child
        # set the child process to be a group leader, so that
        # kill -9 will kill it and all its descendents
        # We have ignore SIGTTOU to let write background processes
        setpgrp(0, 0);

        if ($arg{redirect_stderr} == 1) {
            open STDERR, ">&STDOUT";
        }
        if (scalar(@{$arg{arguments}}) <= 0) {
            exec($arg{command});
        } else {
            exec($arg{command}, @{$arg{arguments}});
        }
        # Exec is in error. No such command maybe.
        exit(127);
    }

    return (0, join("\n", @output), $return_code);
}

sub mymodule_load {
    my (%options) = @_;
    my $file;
    ($file = ($options{module} =~ /\.pm$/ ? $options{module} : $options{module} . '.pm')) =~ s{::}{/}g;
    
    eval {
        local $SIG{__DIE__} = 'IGNORE';
        require $file;
        $file =~ s{/}{::}g;
        $file =~ s/\.pm$//;
    };
    if ($@) {
        $options{logger}->writeLogError('[core] ' . $options{error_msg} . ' - ' . $@);
        return 1;
    }
    return wantarray ? (0, $file) : 0;
}

sub write_file {
    my (%options) = @_;

    File::Path::make_path(File::Basename::dirname($options{filename}));
    my $fh;
    if (!open($fh, '>', $options{filename})) {
        $options{logger}->writeLogError("[core] Cannot open file '$options{filename}': $!");
        return 0;
    }
    print $fh $options{content};
    close $fh;
    return 1;
}

sub trim {
    my ($value) = $_[0];
    
    # Sometimes there is a null character
    $value =~ s/\x00$//;
    $value =~ s/^[ \t\n]+//;
    $value =~ s/[ \t\n]+$//;
    return $value;
}

sub slurp {
    my (%options) = @_;

    my ($fh, $size);
    if (!open($fh, '<', $options{file})) {
        return (0, "Could not open $options{file}: $!");
    }
    my $buffer = do { local $/; <$fh> };
    close $fh;
    return (1, 'ok', $buffer);
}

sub scale {
    my (%options) = @_;

    my ($src_quantity, $src_unit) = (undef, 'B');
    if (defined($options{src_unit}) && $options{src_unit} =~ /([kmgtpe])?(b)/i) {
        $src_quantity = $1;
        $src_unit = $2;
    }
    my ($dst_quantity, $dst_unit) = ('auto', $src_unit);
    if (defined($options{dst_unit}) && $options{dst_unit} =~ /([kmgtpe])?(b)/i) {
        $dst_quantity = $1;
        $dst_unit = $2;
    }

    my $base = 1024;
    $options{value} *= 8 if ($dst_unit eq 'b' && $src_unit eq 'B');
    $options{value} /= 8 if ($dst_unit eq 'B' && $src_unit eq 'b');
    $base = 1000 if ($dst_unit eq 'b');

    my %expo = (k => 1, m => 2, g => 3, t => 4, p => 5, e => 6);
    my $src_expo = 0;
    $src_expo = $expo{ lc($src_quantity) } if (defined($src_quantity));

    if (defined($dst_quantity) && $dst_quantity eq 'auto') {
        my @auto = ('', 'k', 'm', 'g', 't', 'p', 'e');
        for (; $src_expo < scalar(@auto); $src_expo++) {
            last if ($options{value} < $base);
            $options{value} = $options{value} / $base;
        }

        if (defined($options{format}) && $options{format} ne '') {
            $options{value} = sprintf($options{format}, $options{value});
        }
        return ($options{value}, uc($auto[$src_expo]) . $dst_unit);
    }

    my $dst_expo = 0;
    $dst_expo = $expo{ lc($dst_quantity) } if (defined($dst_quantity));
    if ($dst_expo - $src_expo > 0) {
        $options{value} = $options{value} / ($base ** ($dst_expo - $src_expo));
    } elsif ($dst_expo - $src_expo < 0) {
        $options{value} = $options{value} * ($base ** (($dst_expo - $src_expo) * -1));
    }

    if (defined($options{format}) && $options{format} ne '') {
        $options{value} = sprintf($options{format}, $options{value});
    }
    return ($options{value}, $options{dst_unit});
}

1;
