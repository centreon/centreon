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

package modules::core::proxy::sshclient;

use base qw(Libssh::Session);

use strict;
use warnings;
use Libssh::Sftp qw(:all);

sub new {
    my ($class, %options) = @_;
    my $self = $class->SUPER::new(%options);
    bless $self, $class;

    $self->{logger} = $options{logger};
    $self->{sftp} = undef;
    return $self;
}

sub open_session {
    my ($self, %options) = @_;

    if ($self->options(host => $options{ssh_host}, port => $options{ssh_port}, user => $options{ssh_username}) != Libssh::Session::SSH_OK) {
        $self->{logger}->writeLogError('[proxy] -sshclient- options method: ' . $self->error());
        return -1;
    }

    if ($self->connect() != Libssh::Session::SSH_OK) {
        $self->{logger}->writeLogError('[proxy] -sshclient- connect method: ' . $self->error());
        return -1;
    }

    if ($self->auth_publickey_auto() != Libssh::Session::SSH_AUTH_SUCCESS) {
        $self->{logger}->writeLogInfo('[proxy] -sshclient- auth publickey auto failure: ' . $self->error(GetErrorSession => 1));
        if (!defined($options{ssh_password}) || $options{ssh_password} eq '') {
            $self->{logger}->writeLogError('[proxy] -sshclient- auth issue: no password');
            return -1;
        }
        if ($self->auth_password(password => $options{ssh_password}) != Libssh::Session::SSH_AUTH_SUCCESS) {
            $self->{logger}->writeLogError('[proxy] -sshclient- auth issue: ' . $self->error(GetErrorSession => 1));
            return -1;
        }
    }

    $self->{logger}->writeLogInfo('[proxy] -sshclient- authentification succeed');

    $self->{sftp} = Libssh::Sftp->new(session => $self);
    if (!defined($self->{sftp})) {
        $self->{logger}->writeLogError('[proxy] -sshclient- cannot init sftp: ' . Libssh::Sftp::error());
        return -1;
    }

    return 0;
}

sub action {
    my ($self, %options) = @_;

    print "===lall $options{action}====\n";
}

sub close {
    my ($self, %options) = @_;
    
    # to be compatible with zmq close class
}

1;
