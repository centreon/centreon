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

package gorgone::class::sqlquery;

use strict;
use warnings;

sub new {
    my ($class, %options) = @_;
    my $self  = {};
    $self->{logger} = $options{logger};
    $self->{db_centreon} = $options{db_centreon};

    bless $self, $class;
    return $self;
}

sub builder {
    my ($self, %options) = @_;

    my $where = defined($options{where}) ? ' WHERE ' . $options{where} : '';
    my $extra_suffix = defined($options{extra_suffix}) ? $options{extra_suffix} : '';
    my $request = $options{request} . " " . join(', ', @{$options{fields}}) . 
        ' FROM ' . join(', ', @{$options{tables}}) . $where . $extra_suffix;
    return $request;
}

sub do {
    my ($self, %options) = @_;
    my $mode = defined($options{mode}) ? $options{mode} : 0;
    
    my ($status, $sth) = $self->{db_centreon}->query($options{request});
    if ($status == -1) {
        return (-1, undef);
    }
    if ($mode == 0) {
        return ($status, $sth);
    } elsif ($mode == 1) {
        my $result = $sth->fetchall_hashref($options{keys});
        if (!defined($result)) {
            $self->{logger}->writeLogError("[core] Cannot fetch database data: " . $sth->errstr . " [request = $options{request}]");
            return (-1, undef);
        }
        return ($status, $result);
    }
    my $result = $sth->fetchall_arrayref();
    if (!defined($result)) {
        $self->{logger}->writeLogError("[core] Cannot fetch database data: " . $sth->errstr . " [request = $options{request}]");
        return (-1, undef);
    }
    return ($status, $result);
}

sub custom_execute {
    my ($self, %options) = @_;
    
    return $self->do(%options);
}

sub execute {
    my ($self, %options) = @_;
    
    my $request = $self->builder(%options);
    return $self->do(request => $request, %options);
}

sub transaction_query {
    my ($self, %options) = @_;

    $self->transaction_mode(1);
    my ($status) = $self->do(request => $options{request});
    if ($status == -1) {
        $self->rollback();
        return -1;
    }
    
    $self->commit();
    return 0;
}

sub quote {
    my ($self, %options) = @_;

    return $self->{db_centreon}->quote($options{value});
}

sub transaction_mode { shift->{db_centreon}->transaction_mode($_[0]); };

sub commit { shift->{db_centreon}->commit(); }

sub rollback { shift->{db_centreon}->rollback(); }

1;
