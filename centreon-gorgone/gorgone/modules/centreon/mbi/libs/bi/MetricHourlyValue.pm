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

package gorgone::modules::centreon::mbi::libs::bi::MetricHourlyValue;

# Constructor
# parameters:
# $logger: instance of class CentreonLogger
# $centreon: Instance of centreonDB class for connection to Centreon database
# $centstorage: (optionnal) Instance of centreonDB class for connection to Centstorage database
sub new {
	my $class = shift;
	my $self  = {};
	$self->{logger}	= shift;
	$self->{centstorage} = shift;

    $self->{name_minmaxavg_tmp} = 'mod_bi_tmp_minmaxavgvalue';
    if (@_) {
        $self->{name_minmaxavg_tmp} .= $_[0];
    }

	$self->{servicemetrics} = "mod_bi_tmp_today_servicemetrics";
	$self->{name} = "mod_bi_metrichourlyvalue";
	$self->{timeColumn} = "time_id";
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

sub insertValues {
	my $self = shift;
	my $db = $self->{"centstorage"};
	my $logger =  $self->{"logger"};
	
	my $query = "INSERT INTO ".$self->{"name"};
	$query .= " SELECT sm.id as servicemetric_id, t.id as time_id, mmavt.avg_value, mmavt.min_value, mmavt.max_value, m.max , m.warn, m.crit";
	$query .= " FROM " . $self->{name_minmaxavg_tmp} . " mmavt";
	$query .= " JOIN (metrics m, " . $self->{servicemetrics} . " sm, mod_bi_time t)";
	$query .= " ON (mmavt.id_metric = m.metric_id and mmavt.id_metric = sm.metric_id AND mmavt.valueTime = t.dtime)";
	$db->query({ query => $query });
} 

1;
