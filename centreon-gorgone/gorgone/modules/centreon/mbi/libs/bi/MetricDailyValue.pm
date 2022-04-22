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

package gorgone::modules::centreon::mbi::libs::bi::MetricDailyValue;

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
    $self->{name_firstlast_tmp} = 'mod_bi_tmp_firstlastvalues';
    if (@_) {
        $self->{name_minmaxavg_tmp} .= $_[0];
        $self->{name_firstlast_tmp} .= $_[0];
    }

	$self->{today_servicemetrics} = "mod_bi_tmp_today_servicemetrics";
	$self->{name} = "mod_bi_metricdailyvalue";
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

sub dropTempTables {
	my $self = shift;
	my $db = $self->{"centstorage"};
	my $query = "DROP TABLE `" . $self->{name_minmaxavg_tmp} . "`";
	$db->query($query);
	$query = "DROP TABLE `" . $self->{name_firstlast_tmp} . "`";
	$db->query($query);
}

sub insertValues {
	my $self = shift;
	my $db = $self->{"centstorage"};
	my $logger =  $self->{"logger"};
	my $liveServiceId = shift;
	my $timeId = shift;
	
	my $query = "INSERT INTO " . $self->{"name"};
	$query .= " SELECT sm.id as servicemetric_id, '".$timeId."', ".$liveServiceId." as liveservice_id,";
	$query .= " mmavt.avg_value, mmavt.min_value, mmavt.max_value, flvt.`first_value`, flvt.`last_value`, m.max,";
	$query .= " m.warn, m.crit";
	$query .= " FROM " . $self->{name_minmaxavg_tmp} . " mmavt";
	$query .= " JOIN (metrics m, " . $self->{'today_servicemetrics'} . " sm)";
	$query .= " ON (mmavt.id_metric = m.metric_id and mmavt.id_metric = sm.metric_id)";
	$query .= " LEFT JOIN " . $self->{name_firstlast_tmp} . " flvt ON (mmavt.id_metric = flvt.id_metric)";
	$db->query($query);

	$self->dropTempTables();
} 

sub getMetricCapacityValuesOnPeriod {
	my $self = shift;
	my $db = $self->{"centstorage"};
	my $logger =  $self->{"logger"};
	my ($start_time_id, $end_time_id, $etlProperties) = @_;

	my $query =	" SELECT servicemetric_id, liveservice_id, ";
	$query .=		" `first_value`,  total";
	$query .=	" FROM  mod_bi_liveservice l, mod_bi_servicemetrics m, ".$self->{"name"}." v";
	$query .=	" WHERE timeperiod_id IN (".$etlProperties->{'capacity.include.liveservices'}.")";
	$query .= " AND l.id = v.liveservice_id";
	$query .= " AND time_id = ".$start_time_id;
	if (defined($etlProperties->{'capacity.exclude.metrics'}) && $etlProperties->{'capacity.exclude.metrics'} ne "") {
		$query .=		" AND metric_name NOT IN (".$etlProperties->{'capacity.exclude.metrics'}.")";
	}
	$query .=		" AND sc_id IN (".$etlProperties->{'capacity.include.servicecategories'}.")";
	$query .=		" AND v.servicemetric_id = m.id";
	$query .=	" GROUP BY servicemetric_id, liveservice_id";
	my $sth = $db->query($query);
	my %data = ();
	while (my $row = $sth->fetchrow_hashref()) {
		my @table = ($row->{"servicemetric_id"}, $row->{"liveservice_id"}, $row->{first_value}, $row->{"total"});
		$data{$row->{"servicemetric_id"}.";".$row->{"liveservice_id"}} = \@table;
	}
	
	$query = 	" SELECT servicemetric_id, liveservice_id, ";
	$query .= 		"`last_value`, total";
	$query .= 	" FROM mod_bi_liveservice l, mod_bi_servicemetrics m, ".$self->{"name"}." v";
	$query .=	" WHERE timeperiod_id IN (".$etlProperties->{'capacity.include.liveservices'}.")";
	$query .= " AND l.id = v.liveservice_id";
	$query .= " AND time_id = ".$end_time_id;
	if (defined($etlProperties->{'capacity.exclude.metrics'}) && $etlProperties->{'capacity.exclude.metrics'} ne "") {
		$query .=		" AND metric_name NOT IN (".$etlProperties->{'capacity.exclude.metrics'}.")";
	}
	$query .=		" AND sc_id IN (".$etlProperties->{'capacity.include.servicecategories'}.")";
	$query .=		" AND v.servicemetric_id = m.id";
	$query .=	" GROUP BY servicemetric_id, liveservice_id";

	$sth = $db->query($query);
	while (my $row = $sth->fetchrow_hashref()) {
		my $entry =  $data{$row->{servicemetric_id} . ';' . $row->{liveservice_id}};
		if (defined($entry)) {
			$entry->[4] = $row->{last_value};
			$entry->[5] = $row->{total};
		} else {
			my @table;
			$table[0] = $row->{servicemetric_id};
			$table[1] = $row->{liveservice_id};
			$table[4] = $row->{last_value};
			$table[5] = $row->{total};
			$data{$row->{servicemetric_id} . ';' . $row->{liveservice_id}} = \@table;
		}
	}
	return \%data;
}

1;
