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

package gorgone::modules::centreon::audit::metrics::centreon::database;

use warnings;
use strict;

sub metrics {
    my (%options) = @_;

    return undef if (!defined($options{centstorage_sqlquery}));

    my $metrics = {
        status_code => 0,
        status_message => 'ok',
        space_free_bytes => 0,
        space_used_bytes => 0,
        databases => {}
    };

    my ($status, $datas) = $options{centstorage_sqlquery}->custom_execute(
        request => q{show variables like 'innodb_file_per_table'},
        mode => 2
    );
    if ($status == -1 || !defined($datas->[0])) {
        $metrics->{status_code} = 1;
        $metrics->{status_message} = 'cannot get innodb_file_per_table configuration';
        return $metrics;
    }
    my $innodb_per_table = 0;
    $innodb_per_table = 1 if ($datas->[0]->[1] =~ /on/i);

    ($status, $datas) = $options{centstorage_sqlquery}->custom_execute(
        request => q{SELECT table_schema, table_name, engine, data_free, data_length+index_length as data_used, (DATA_FREE / (DATA_LENGTH+INDEX_LENGTH)) as TAUX_FRAG FROM information_schema.tables WHERE table_type = 'BASE TABLE' AND engine IN ('InnoDB', 'MyISAM')},
        mode => 2
    );
    if ($status == -1 || !defined($datas->[0])) {
        $metrics->{status_code} = 1;
        $metrics->{status_message} = 'cannot get schema information';
        return $metrics;
    }

    my $innodb_ibdata_done = 0;
    foreach my $row (@$datas) {
        if (!defined($metrics->{databases}->{ $row->[0] })) {
            $metrics->{databases}->{ $row->[0] } = {
                space_free_bytes => 0,
                space_used_bytes => 0,
                tables => {}
            };
        }

        $metrics->{databases}->{ $row->[0] }->{tables}->{ $row->[1] } = {};

        # For a table located in the shared tablespace, this is the free space of the shared tablespace.
        if ($row->[2] !~ /innodb/i || $innodb_per_table == 1) {
            $metrics->{space_free_bytes} += $row->[3];
            $metrics->{databases}->{ $row->[0] }->{space_free_bytes} += $row->[3];
            $metrics->{databases}->{ $row->[0] }->{tables}->{ $row->[1] }->{space_free_bytes} = $row->[3];
            $metrics->{databases}->{ $row->[0] }->{tables}->{ $row->[1] }->{frag} = $row->[5];
        } elsif ($innodb_ibdata_done == 0) {
            $metrics->{space_free_bytes} += $row->[3];
            $innodb_ibdata_done = 1;
        }
        $metrics->{space_used_bytes} += $row->[4];
        $metrics->{databases}->{ $row->[0] }->{space_used_bytes} += $row->[4];
        $metrics->{databases}->{ $row->[0] }->{tables}->{ $row->[1] }->{space_used_bytes} = $row->[4];
        $metrics->{databases}->{ $row->[0] }->{tables}->{ $row->[1] }->{engine} = $row->[2];
    }

    my $rm_table_size = 10 * 1024 * 1024;

    $metrics->{space_free_human} = join('', gorgone::standard::misc::scale(value => $metrics->{space_free_bytes}, format => '%.2f'));
    $metrics->{space_used_human} = join('', gorgone::standard::misc::scale(value => $metrics->{space_used_bytes}, format => '%.2f'));
    foreach my $db (keys %{$metrics->{databases}}) {
        $metrics->{databases}->{$db}->{space_used_human} = join('', gorgone::standard::misc::scale(value => $metrics->{databases}->{$db}->{space_used_bytes}, format => '%.2f'));
        $metrics->{databases}->{$db}->{space_free_human} = join('', gorgone::standard::misc::scale(value => $metrics->{databases}->{$db}->{space_free_bytes}, format => '%.2f'));
        foreach my $table (keys %{$metrics->{databases}->{$db}->{tables}}) {
            if ($metrics->{databases}->{$db}->{tables}->{$table}->{space_used_bytes} < $rm_table_size) {
                delete $metrics->{databases}->{$db}->{tables}->{$table};
                next;
            }
            $metrics->{databases}->{$db}->{tables}->{$table}->{space_free_human} = join('', gorgone::standard::misc::scale(value => $metrics->{databases}->{$db}->{tables}->{$table}->{space_free_bytes}, format => '%.2f'))
                if (defined($metrics->{databases}->{$db}->{tables}->{$table}->{space_free_bytes}));
            $metrics->{databases}->{$db}->{tables}->{$table}->{space_used_human} = join('', gorgone::standard::misc::scale(value => $metrics->{databases}->{$db}->{tables}->{$table}->{space_used_bytes}, format => '%.2f'));
        }
    }

    return $metrics;
}

1;
