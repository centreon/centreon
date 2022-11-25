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

package gorgone::class::logger;

=head1 NOM

gorgone::class::logger - Simple logging module

=head1 SYNOPSIS

 #!/usr/bin/perl -w

 use strict;
 use warnings;

 use centreon::polling;

 my $logger = new gorgone::class::logger();

 $logger->writeLogInfo("information");

=head1 DESCRIPTION

This module offers a simple interface to write log messages to various output:

* standard output
* file
* syslog

=cut

use strict;
use warnings;
use Sys::Syslog qw(:standard :macros);
use IO::Handle;
use Encode;

my %severities = (
    1 => LOG_INFO,
    2 => LOG_ERR,
    4 => LOG_DEBUG
);

sub new {
    my $class = shift;

    my $self = bless
      {
       file => 0,
       filehandler => undef,
       # 0 = nothing, 1 = critical, 3 = info, 7 = debug
       severity => 3,
       old_severity => 3,
       # 0 = stdout, 1 = file, 2 = syslog
       log_mode => 0,
       # Output pid of current process
       withpid => 0,
       # syslog
       log_facility => undef,
       log_option => LOG_PID,
      }, $class;
    return $self;
}

sub file_mode($$) {
    my ($self, $file) = @_;

    if (defined($self->{filehandler})) {
        $self->{filehandler}->close();
    }
    if (open($self->{filehandler}, ">>", $file)){
        $self->{log_mode} = 1;
        $self->{filehandler}->autoflush(1);
        $self->{file_name} = $file;
        return 1;
    }
    $self->{filehandler} = undef;
    print STDERR "Cannot open file $file: $!\n";
    return 0;
}

sub is_file_mode {
    my $self = shift;
    
    if ($self->{log_mode} == 1) {
        return 1;
    }
    return 0;
}

sub is_debug {
    my $self = shift;
    
    if (($self->{severity} & 4) == 0) {
        return 0;
    }
    return 1;
}

sub syslog_mode($$$) {
    my ($self, $logopt, $facility) = @_;

    $self->{log_mode} = 2;
    openlog($0, $logopt, $facility);
    return 1;
}

# For daemons
sub redirect_output {
    my $self = shift;

    if ($self->is_file_mode()) {
        open my $lfh, '>>', $self->{file_name};
        open STDOUT, '>&', $lfh;
        open STDERR, '>&', $lfh;
    }
}

sub flush_output {
    my ($self, %options) = @_;

    $| = 1 if (defined($options{enabled}));
}

sub force_default_severity {
    my ($self, %options) = @_;

    $self->{old_severity} = defined($options{severity}) ? $options{severity} : $self->{severity};
}

sub set_default_severity {
    my $self = shift;

    $self->{severity} = $self->{old_severity};
}

# Getter/Setter Log severity
sub severity {
    my $self = shift;
    if (@_) {
        my $save_severity = $self->{severity};
        if ($_[0] =~ /^[012347]$/) {
            $self->{severity} = $_[0];
        } elsif ($_[0] eq 'none') {
            $self->{severity} = 0;
        } elsif ($_[0] eq 'error') {
            $self->{severity} = 1;
        } elsif ($_[0] eq 'info') {
            $self->{severity} = 3;
        } elsif ($_[0] eq 'debug') {
            $self->{severity} = 7;
        } else {
            $self->writeLogError('Wrong severity value set.');
            return -1;
        }
        $self->{old_severity} = $save_severity;
    }
    return $self->{severity};
}

sub withpid {
    my $self = shift;
    if (@_) {
        $self->{withpid} = $_[0];
    }
    return $self->{withpid};
}

sub get_date {
    my $self = shift;
    my ($sec,$min,$hour,$mday,$mon,$year,$wday,$yday,$isdst) = localtime(time());
    return sprintf(
        '%04d-%02d-%02d %02d:%02d:%02d', 
        $year+1900, $mon+1, $mday, $hour, $min, $sec
    );
}

sub writeLog {
    my ($self) = shift;

    my $withdate = (defined $_[0]->{withdate}) ? $_[0]->{withdate} : 1;
    my $withseverity = (defined $_[0]->{withseverity}) ? $_[0]->{withseverity} : 1;

    if (($self->{severity} & $_[0]->{severity}) == 0) {
        return;
    }

    if (length($_[0]->{message}) > 20000) {
        $_[0]->{message} = substr($_[0]->{message}, 0, 20000) . '...';
    }
    if ($self->{log_mode} == 2) {
        syslog($severities{$_[0]->{severity}}, $_[0]->{message});
        return;
    }

    $_[0]->{message} = (($self->{withpid} == 1) ? "$$ - $_[0]->{message} " : $_[0]->{message});
    $_[0]->{message} = ($withseverity)
      ? $_[0]->{severity_str} . " - $_[0]->{message}" : $_[0]->{message};
    $_[0]->{message} = ($withdate)
      ? $self->get_date . " - $_[0]->{message}" : $_[0]->{message};

    chomp($_[0]->{message});
    if ($self->{log_mode} == 0) {
        print "$_[0]->{message}\n";
    } elsif ($self->{log_mode} == 1) {
        if (defined $self->{filehandler}) {
            print { $self->{filehandler} } "$_[0]->{message}\n";
        }
    }
}

sub writeLogDebug {
    my ($self) = shift;
    
    $self->writeLog({ severity => 4, severity_str => 'DEBUG', message => $_[0] });
}

sub writeLogInfo {
    my ($self) = shift;
    
    $self->writeLog({ severity => 2, severity_str => 'INFO', message => $_[0] });
}

sub writeLogError {
    my ($self) = shift;
    
    $self->writeLog({ severity => 1, severity_str => 'ERROR', message => $_[0] });
}

sub DESTROY {
    my $self = shift;

    if (defined $self->{filehandler}) {
        $self->{filehandler}->close();
    }
}

1;
