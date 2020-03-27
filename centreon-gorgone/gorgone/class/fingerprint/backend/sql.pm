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

package gorgone::class::fingerprint::backend::sql;

use base qw(gorgone::class::db);

use strict;
use warnings;

sub new {
    my ($class, %options) = @_;
    my $self = $class->SUPER::new(
        logger   => $options{logger},
        type     => defined($options{config}->{gorgone_db_type}) && $options{config}->{gorgone_db_type} ne '' ?
            $options{config}->{gorgone_db_type} : $options{config_core}->{gorgone_db_type},
        db       => defined($options{config}->{gorgone_db_name}) && $options{config}->{gorgone_db_name} ne '' ?
            $options{config}->{gorgone_db_name} : $options{config_core}->{gorgone_db_name},
        host     => defined($options{config}->{gorgone_db_host}) && $options{config}->{gorgone_db_host} ne '' ?
            $options{config}->{gorgone_db_host} : $options{config_core}->{gorgone_db_host},
        port     => defined($options{config}->{gorgone_db_port}) && $options{config}->{gorgone_db_port} ne '' ?
            $options{config}->{gorgone_db_port} : $options{config_core}->{gorgone_db_port},
        user     => defined($options{config}->{gorgone_db_user}) && $options{config}->{gorgone_db_user} ne '' ?
            $options{config}->{gorgone_db_user} : $options{config_core}->{gorgone_db_user},
        password => defined($options{config}->{gorgone_db_password}) && $options{config}->{gorgone_db_password} ne '' ?
            $options{config}->{gorgone_db_password} : $options{config_core}->{gorgone_db_password},
        force    => 2
    );
    bless $self, $class;

    $self->{fingerprint_mode} = $options{config_core}->{fingerprint_mode};

    return $self;
}

sub check_fingerprint {
    my ($self, %options) = @_;

    return 1 if ($self->{fingerprint_mode} eq 'always');

    my ($status, $sth) = $self->query("SELECT `id`, `fingerprint` FROM gorgone_target_fingerprint WHERE target = " . $self->quote($options{target}) . " ORDER BY id ASC LIMIT 1");
    return (0, "cannot get fingerprint for target '$options{target}'") if ($status == -1);
    my $row = $sth->fetchrow_hashref();

    if (!defined($row)) {
        if ($self->{fingerprint_mode} eq 'strict') {
            return (0, "no fingerprint found for target '" . $options{target} . "' [strict mode] [fingerprint: $options{fingerprint}]");
        }
        ($status) = $self->query("INSERT INTO gorgone_target_fingerprint (`target`, `fingerprint`) VALUES (" 
            . $self->quote($options{target}) . ', ' . $self->quote($options{fingerprint}) . ')');
        return (0, "cannot insert target '$options{target}' fingerprint") if ($status == -1);
        return 1;
    }

    if ($row->{fingerprint} ne $options{fingerprint}) {
        return (0, "fingerprint changed for target '" . $options{target} . "' [id: $row->{id}] [old fingerprint: $row->{fingerprint}] [new fingerprint: $options{fingerprint}]");
    }
    return 1;
}

1;

__END__
