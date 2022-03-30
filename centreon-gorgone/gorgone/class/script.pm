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
use YAML::XS;
use Hash::Merge;
Hash::Merge::set_behavior('RIGHT_PRECEDENT');
$YAML::XS::Boolean = 'JSON::PP';
$YAML::XS::LoadBlessed = 1;

$SIG{__DIE__} = sub {
    my $error = shift;
    print "Error: $error";
    exit 1;
};

sub new {
    my ($class, $name, %options) = @_;
    my %defaults = (
       log_file => undef,
       centreon_db_conn => 0,
       centstorage_db_conn => 0,
       severity => 'info',
       noroot => 0
    );
    my $self = {%defaults, %options};

    bless $self, $class;
    $self->{name} = $name;
    $self->{logger} = gorgone::class::logger->new();
    $self->{options} = {
        'config=s'    => \$self->{config_file},
        'logfile=s'   => \$self->{log_file},
        'severity=s'  => \$self->{severity},
        'flushoutput' => \$self->{flushoutput},
        'help|?'      => \$self->{help},
        'version'     => \$self->{version}
    };
    return $self;
}

sub init {
    my $self = shift;

    if (defined $self->{log_file}) {
        $self->{logger}->file_mode($self->{log_file});
    }
    $self->{logger}->flush_output(enabled => $self->{flushoutput});
    $self->{logger}->severity($self->{severity});
    $self->{logger}->force_default_severity();

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
    if ($self->{version}) {
        print "version: " . $self->get_version() . "\n";
        exit(0);
    }
}

sub run {
    my $self = shift;

    $self->parse_options();
    $self->init();
}

sub yaml_get_include {
    my ($self, %options) = @_;

    my @all_files = ();
    my @dirs = split(/,/, $options{include});
    foreach my $dir (@dirs) {
        next if ($dir eq '');
        my $dirname = File::Basename::dirname($dir);
        $dirname = $options{current_dir} . '/' . $dirname if ($dirname !~ /^\//);
        my $match_files = File::Basename::basename($dir);
        $match_files =~ s/\*/\\E.*\\Q/g;
        $match_files = '\Q' . $match_files . '\E';

        my @sorted_files = ();
        my $DIR;
        if (!opendir($DIR, $dirname)) {
            $self->{logger}->writeLogError("config - cannot opendir '$dirname' error: $!");
            return ();
        }

        while (readdir($DIR)) {
            if (-f "$dirname/$_" && eval "/^$match_files\$/") {
                push @sorted_files, "$dirname/$_";
            }
        }
        closedir($DIR);
        @sorted_files = sort { $a cmp $b } @sorted_files;
        push @all_files, @sorted_files;
    }

    return @all_files;
}

sub yaml_parse_config {
    my ($self, %options) = @_;

    if (ref(${$options{config}}) eq 'HASH') {
        foreach (keys %{${$options{config}}}) {
            my $ariane = $options{ariane} . $_ . '##';
            if (defined($options{filter}) && eval "$options{filter}") {
                delete ${$options{config}}->{$_};
                next;
            }
            $self->yaml_parse_config(
                config => \${$options{config}}->{$_},
                current_dir => $options{current_dir},
                filter => $options{filter},
                ariane => $ariane
            );
        }
    } elsif (ref(${$options{config}}) eq 'ARRAY') {
        my $size = @{${$options{config}}};
        my $ariane = $options{ariane} . 'ARRAY##';
        for (my $i = 0; $i < $size; $i++) {
            if (defined($options{filter}) && eval "$options{filter}") {
                ${$options{config}} = undef;
                last;
            }
            $self->yaml_parse_config(
                config => \${$options{config}}->[$i],
                current_dir => $options{current_dir},
                filter => $options{filter},
                ariane => $ariane
            );
        }
    } elsif (ref(${$options{config}}) eq 'include') {
        my @files = $self->yaml_get_include(
            include => ${${$options{config}}},
            current_dir => $options{current_dir},
            filter => $options{filter}
        );
        ${$options{config}} = undef;
        foreach (@files) {
            if (! -r $_) {
                $self->{logger}->writeLogError("config - cannot read file '$_'");
                next;
            }
            my $config = $self->yaml_load_config(file => $_, filter => $options{filter}, ariane => $options{ariane});
            next if (!defined($config));
            if (ref($config) eq 'ARRAY') {
                ${$options{config}} = [] if (ref(${$options{config}}) ne 'ARRAY');
                push @{${$options{config}}}, @$config;
            } elsif (ref($config) eq 'HASH') {
                ${$options{config}} = {} if (ref(${$options{config}}) ne 'HASH');
                ${$options{config}} = Hash::Merge::merge(${$options{config}}, $config);
            } else {
                ${$options{config}} = $config;
            }
        }
    } elsif (ref(${$options{config}}) eq 'JSON::PP::Boolean') {
        if (${${$options{config}}}) {
            ${$options{config}} = 'true';
        } else {
            ${$options{config}} = 'false';
        }
    }
}

sub yaml_load_config {
    my ($self, %options) = @_;

    my $config;
    eval {
        $config = YAML::XS::LoadFile($options{file});
    };
    if ($@) {
        $self->{logger}->writeLogError("config - yaml load file '$options{file}' error: $@");
        return undef;
    }

    my $current_dir = File::Basename::dirname($options{file});
    $self->yaml_parse_config(
        config => \$config,
        current_dir => $current_dir,
        filter => $options{filter},
        ariane => defined($options{ariane}) ? $options{ariane} : ''
    );
    return $config;
}

1;
