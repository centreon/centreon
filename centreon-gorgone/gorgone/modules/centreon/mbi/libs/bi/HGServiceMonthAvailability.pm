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

package gorgone::modules::centreon::mbi::libs::bi::HGServiceMonthAvailability;

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
	$self->{'name'} = "mod_bi_hgservicemonthavailability";
	$self->{'timeColumn'} = "time_id";
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
	my ($time_id, $data) = @_;
    my $insertParam = 1000;

	my $query_start = "INSERT INTO `".$self->{'name'}."`".
        " (`time_id`, `modbihg_id`, `modbihc_id`, `modbisc_id`, `liveservice_id`, `available`,".
        " `unavailable_time`, `degraded_time`, `alert_unavailable_opened`, `alert_unavailable_closed`, ".
        " `alert_degraded_opened`, `alert_degraded_closed`, ".
        " `alert_other_opened`, `alert_other_closed`, ".				
        " `alert_degraded_total`, `alert_unavailable_total`,".
        " `alert_other_total`, `mtrs`, `mtbf`, `mtbsi`)".
        " VALUES ";
	my $counter = 0;
    my $query = $query_start;
    my $append = '';

	foreach my $entry (@$data) {
		my $size = scalar(@$entry);

        $query .= $append . "($time_id";
		for (my $i = 0; $i < $size; $i++) {
            $query .= ', ' . (defined($entry->[$i]) ? $entry->[$i] : 'NULL');
		}
        $query .= ')';

		$append = ',';
		$counter++;
        if ($counter >= $insertParam) {
            $self->{centstorage}->query({ query => $query });
            $query = $query_start;
			$counter = 0;
            $append = '';
		}
	}
	$self->{centstorage}->query({ query => $query }) if ($counter > 0);
}

1;
