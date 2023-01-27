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

package gorgone::modules::centreon::mbi::libs::bi::BIServiceCategory;

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
	bless $self, $class;
	return $self;
}


sub getAllEntries {
	my $self = shift;
	my $db = $self->{"centstorage"};

	my $query = "SELECT `sc_id`, `sc_name`";
	$query .= " FROM `mod_bi_servicecategories`";
	my $sth = $db->query($query);
	my @entries = ();
	while (my $row = $sth->fetchrow_hashref()) {
		push @entries, $row->{"sc_id"}.";".$row->{"sc_name"};
	}
	$sth->finish();
	return (\@entries);
}

sub getEntryIds {
	my $self = shift;
	my $db = $self->{"centstorage"};

	my $query = "SELECT `id`, `sc_id`, `sc_name`";
	$query .= " FROM `mod_bi_servicecategories`";
	my $sth = $db->query($query);
	my %entries = ();
	while (my $row = $sth->fetchrow_hashref()) {
		$entries{$row->{"sc_id"}.";".$row->{"sc_name"}} = $row->{"id"};
	}
	$sth->finish();
	return (\%entries);
}

sub entryExists {
	my $self = shift;
	my ($value, $entries) = (shift, shift);
	foreach(@$entries) {
		if ($value eq $_) {
			return 1;
		}
	}
	return 0;
}
sub insert {
	my $self = shift;
	my $db = $self->{"centstorage"};
	my $logger =  $self->{"logger"};
	my $data = shift;
	my $query = "INSERT INTO `mod_bi_servicecategories`".
				" (`sc_id`, `sc_name`)".
				" VALUES (?,?)";
	my $sth = $db->prepare($query);	
	my $inst = $db->getInstance;
	$inst->begin_work;
	my $counter = 0;
	
	my $existingEntries = $self->getAllEntries;
	foreach (@$data) {
		if (!$self->entryExists($_, $existingEntries)) {
			my ($sc_id, $sc_name) = split(";", $_);
			$sth->bind_param(1, $sc_id);
			$sth->bind_param(2, $sc_name);
			$sth->execute;
			if (defined($inst->errstr)) {
		  		$logger->writeLog("FATAL", "servicecategories insertion execute error : ".$inst->errstr);
			}
			if ($counter >= 1000) {
				$counter = 0;
				$inst->commit;
				if (defined($inst->errstr)) {
		  			$logger->writeLog("FATAL", "servicecategories insertion commit error : ".$inst->errstr);
				}
				$inst->begin_work;
			}
			$counter++;
		}
	}
	$inst->commit;
}

sub truncateTable {
	my $self = shift;
	my $db = $self->{"centstorage"};
	
	my $query = "TRUNCATE TABLE `mod_bi_servicecategories`";
	$db->query($query);
	$db->query("ALTER TABLE `mod_bi_servicecategories` AUTO_INCREMENT=1");
}

1;
