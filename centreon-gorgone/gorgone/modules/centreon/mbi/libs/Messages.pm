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

use strict;
use warnings;

package gorgone::modules::centreon::mbi::libs::Messages;

sub new {
    my $class = shift;
    my $self  = {};

    $self->{messages} = [];

    bless $self, $class;
    return $self;
}

sub writeLog {
    my ($self, $severity, $message, $nodie) = @_;

    $severity = lc($severity);

    my %severities = ('debug' => 'D', 'info' => 'I', 'warning' => 'I', 'error' => 'E', 'fatal' => 'F');
    if ($severities{$severity} eq 'E' || $severities{$severity} eq 'F') {
        die $message if (!defined($nodie) || $nodie == 0);
    }

    push @{$self->{messages}}, [$severities{$severity}, $message];
}

sub getLogs {
    my ($self) = @_;

    return $self->{messages};
}

1;
