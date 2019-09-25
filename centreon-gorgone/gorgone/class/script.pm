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

package gorgone::class::script;

use strict;
use warnings;
use FindBin;
use Getopt::Long;
use Pod::Usage;
use gorgone::class::logger;
use gorgone::class::db;
use gorgone::class::lock;

use vars qw($centreon_config);

$SIG{__DIE__} = sub {
    my $error = shift;
    print "Error: $error";
    exit 1;
};

sub new {
    my ($class, $name, %options) = @_;
    my %defaults = (
       config_file => '/etc/centreon/centreon-config.pm',
       log_file => undef,
       centreon_db_conn => 0,
       centstorage_db_conn => 0,
       severity => 'info',
       noconfig => 0,
       noroot => 0
    );
    my $self = {%defaults, %options};

    bless $self, $class;
    $self->{name} = $name;
    $self->{logger} = gorgone::class::logger->new();
    $self->{options} = {
        'config=s'   => \$self->{config_file},
        'logfile=s'  => \$self->{log_file},
        'severity=s' => \$self->{severity},
        'help|?'     => \$self->{help}
    };
    return $self;
}

sub init {
    my $self = shift;

    if (defined $self->{log_file}) {
        $self->{logger}->file_mode($self->{log_file});
    }
    $self->{logger}->severity($self->{severity});

    if ($self->{noroot} == 1) {
        # Stop exec if root
        if ($< == 0) {
            $self->{logger}->writeLogError("Can't execute script as root.");
            die('Quit');
        }
    }

    if ($self->{centreon_db_conn}) {
        $self->{cdb} = gorgone::class::db->new(
            db => $self->{centreon_config}->{centreon_db},
            host => $self->{centreon_config}->{db_host},
            user => $self->{centreon_config}->{db_user},
            password => $self->{centreon_config}->{db_passwd},
            logger => $self->{logger}
        );
        $self->{lock} = gorgone::class::lock::sql->new($self->{name}, dbc => $self->{cdb});
        $self->{lock}->set();
    }
    if ($self->{centstorage_db_conn}) {
        $self->{csdb} = gorgone::class::db->new(
            db => $self->{centreon_config}->{centstorage_db},
            host => $self->{centreon_config}->{db_host},
            user => $self->{centreon_config}->{db_user},
            password => $self->{centreon_config}->{db_passwd},
            logger => $self->{logger}
        );
    }
}

sub DESTROY {
    my $self = shift;

    if (defined $self->{cdb}) {
        $self->{cdb}->disconnect();
    }
    if (defined $self->{csdb}) {
        $self->{csdb}->disconnect();
    }
}

sub add_options {
    my ($self, %options) = @_;

    $self->{options} = {%{$self->{options}}, %options};
}

sub parse_options {
    my $self = shift;

    Getopt::Long::Configure('bundling');
    die "Command line error" if (!GetOptions(%{$self->{options}}));
    pod2usage(-exitval => 1, -input => $FindBin::Bin . "/" . $FindBin::Script) if ($self->{help});
    if ($self->{noconfig} == 0) {
        require $self->{config_file};
        $self->{centreon_config} = $centreon_config;
    }
}

sub run {
    my $self = shift;

    $self->parse_options();
    $self->init();
}

1;
