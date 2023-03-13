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

package gorgone::modules::centreon::mbi::libs::bi::MetricMonthCapacity;

# Constructor
# parameters:
# $logger: instance of class CentreonLogger
# $centreon: Instance of centreonDB class for connection to Centreon database
# $centstorage: (optionnal) Instance of centreonDB class for connection to Centstorage database
sub new {
	my $class = shift;
	my $self  = {};
	$self->{"logger"}	= shift;
	$self->{"centstorage"} = shift;
	if (@_) {
		$self->{"centreon"}  = shift;
	}
	$self->{"name"} = "mod_bi_metricmonthcapacity";
	$self->{"timeColumn"} = "time_id";
	bless $self, $class;
	return $self;
}

sub getName() {
	my $self = shift;
	return $self->{'name'};
}

sub getTimeColumn() {
	my $self = shift;
	return $self->{'timeColumn'};
}

sub insertStats {
    my $self = shift;
    my $db = $self->{centstorage};
    my ($time_id, $data) = @_;
    my $insertParam = 5000;

    my $query_start = "INSERT INTO `" . $self->{name} . "`".
        "(`time_id`, `servicemetric_id`, `liveservice_id`,".
        " `first_value`, `first_total`, `last_value`, `last_total`)".
        " VALUES ";
    my $counter = 0;
    my $query = $query_start;
    my $append = '';

    while (my ($key, $entry) = each %$data) {
        $query .= $append . "($time_id";

        for (my $i = 0; $i <= 5; $i++) {
            $query .= ', ' . (defined($entry->[$i]) ? $entry->[$i] : 'NULL');
        }
        $query .= ')';

        $append = ',';
        $counter++;
        if ($counter >= $insertParam) {
            $db->query({ query => $query });
            $query = $query_start;
            $counter = 0;
            $append = '';
        }
    }
    $db->query({ query => $query }) if ($counter > 0);
}

1;
