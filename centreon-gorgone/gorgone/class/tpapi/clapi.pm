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

package gorgone::class::tpapi::clapi;

use strict;
use warnings;

sub new {
    my ($class, %options) = @_;
    my $self  = {};
    bless $self, $class;

    $self->{is_error} = 1;
    $self->{error} = 'configuration missing';
    $self->{username} = undef;
    $self->{password} = undef;

    return $self;
}

sub error {
    my ($self, %options) = @_;

    return $self->{error};
}

sub get_username {
    my ($self, %options) = @_;

    if ($self->{is_error} == 1) {
        return undef;
    }

    return $self->{username};
}

sub get_password {
    my ($self, %options) = @_;

    if ($self->{is_error} == 1) {
        return undef;
    }

    if (defined($options{protected}) && $options{protected} == 1) {
        my $password = $self->{password};
        $password =~ s/\$/\\\$/g;
        $password =~ s/"/\\"/g;
        return $password;
    }

    return $self->{password};
}

sub set_configuration {
    my ($self, %options) = @_;

    if (!defined($options{config}) ||
        !defined($options{config}->{username}) ||
         $options{config}->{username} eq '') {
        $self->{error} = 'username configuration missing';
        return 1;
    }

    if (!defined($options{config}->{password}) ||
         $options{config}->{password} eq '') {
        $self->{error} = 'password configuration missing';
        return 1;
    }

    $self->{is_error} = 0;
    $self->{username} = $options{config}->{username};
    $self->{password} = $options{config}->{password};
    return 0;
}

sub get_applycfg_command {
    my ($self, %options) = @_;

    if ($self->{is_error} == 1) {
        return undef;
    }

    return 'centreon -u "' . $self->{username} . '" -p "' . $self->get_password(protected => 1) . '" -a APPLYCFG -v ' . $options{poller_id};
}

1;
