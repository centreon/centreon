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

package gorgone::class::tpapi;

use strict;
use warnings;

sub new {
    my ($class, %options) = @_;
    my $self  = {};
    bless $self, $class;

    $self->{configs} = {};

    return $self;
}

sub get_configuration {
    my ($self, %options) = @_;

    return $self->{configs}->{ $options{name} };
}

sub load_configuration {
    my ($self, %options) = @_;

    $self->{configs} = {};
    return if (!defined($options{configuration}));

    foreach my $config (@{$options{configuration}}) {
        next if (!defined($config->{name}));

        $self->{configs}->{ $config->{name} } = $config;
    }
}

1;
