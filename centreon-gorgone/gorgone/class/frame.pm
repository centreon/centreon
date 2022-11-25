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

package gorgone::class::frame;

use strict;
use warnings;

use JSON::XS;
use Try::Tiny;

sub new {
    my ($class, %options) = @_;
    my $self  = {};
    bless $self, $class;

    if (defined($options{rawData})) {
        $self->setRawData($options{rawData});
    }
    if (defined($options{data})) {
        $self->setData($options{data});
    }

    return $self;
}

sub setData {
    my ($self) = shift;

    $self->{data} = $_[0];
}

sub setRawData {
    my ($self) = shift;

    $self->{rawData} = $_[0];
}

sub setFrame {
    my ($self) = shift;

    $self->{frame} = $_[0];
}

sub getFrame {
    my ($self) = shift;

    return $self->{frame};
}

sub getLastError {
    my ($self) = shift;

    return $self->{lastError};
}

sub decrypt {
    my ($self, $options) = (shift, shift);

    my $plaintext;
    try {
        $plaintext = $options->{cipher}->decrypt(${$self->{frame}}, $options->{key}, $options->{iv});
    };
    if (defined($plaintext) && $plaintext =~ /^\[[A-Za-z0-9_\-]+?\]/) {
        $self->{frame} = \$plaintext;
        return 0;
    }

    $self->{lastError} = $_ ? $_ : 'no message';
    return 1;
}

sub parse {
    my ($self, $options) = (shift, shift);

    if (${$self->{frame}} =~ /^\[(.+?)\]\s+\[(.*?)\]\s+\[(.*?)\]\s+/g) {
        $self->{action} = $1;
        $self->{token} = $2;
        $self->{target} = $3;
        
        if (defined($options) && defined($options->{decode})) {
            try {
                $self->{data} = JSON::XS->new->decode(substr(${$self->{frame}}, pos(${$self->{frame}})));
            } catch {
                $self->{lastError} = $_;
                return 1;
            }
        } else {
            $self->{rawData} = substr(${$self->{frame}}, pos(${$self->{frame}}));
        }

        if (defined($options) && defined($options->{releaseFrame})) {
            $self->{frame} = undef;
        }

        return 0;
    }

    return 1;
}

sub getData {
    my ($self) = shift;

    if (!defined($self->{data})) {
        try {
            $self->{data} = JSON::XS->new->decode($self->{rawData});
        } catch {
            $self->{lastError} = $_;
            return undef;
        }
    }

    return $self->{data};
}

sub decodeData {
    my ($self) = shift;

    if (!defined($self->{data})) {
        try {
            $self->{data} = JSON::XS->new->decode($self->{rawData});
        } catch {
            $self->{lastError} = $_;
            return undef;
        }
    }

    return $self->{data};
}

sub getRawData {
    my ($self) = shift;

    if (!defined($self->{rawData})) {
        try {
            $self->{rawData} = JSON::XS->new->encode($self->{data});
        } catch {
            $self->{lastError} = $_;
            return undef;
        }
    }
    return \$self->{rawData};
}

sub getAction {
    my ($self) = shift;

    return $self->{action};
}

sub getToken {
    my ($self) = shift;

    return $self->{token};
}

sub getTarget {
    my ($self) = shift;

    return $self->{target};
}

sub DESTROY {
    my ($self) = shift;

    $self->{frame} = undef;
    $self->{data} = undef;
    $self->{rawData} = undef;
}

1;
