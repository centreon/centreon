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

package gorgone::modules::centreon::mbi::libs::centreon::CentileProperties;

# Constructor
# parameters:
# $logger: instance of class CentreonLogger
# $centreon: Instance of centreonDB class for connection to Centreon database
# $centstorage: (optionnal) Instance of centreonDB class for connection to Centstorage database
sub new {
    my $class = shift;
    my $self  = {};
    $self->{logger}	= shift;
    $self->{centreon} = shift;
    if (@_) {
        $self->{centstorage}  = shift;
    }
    bless $self, $class;
    return $self;
}

sub getCentileParams {
    my $self = shift;
    my $centreon = $self->{centreon};
    my $logger = $self->{logger};
    
    my $centileParams = [];
    my $query = "SELECT `centile_param`, `timeperiod_id` FROM `mod_bi_options_centiles`";
    my $sth = $centreon->query({ query => $query });
    while (my $row = $sth->fetchrow_hashref()) {
    	if (defined($row->{centile_param}) && $row->{centile_param} ne '0' && defined($row->{timeperiod_id}) && $row->{timeperiod_id} ne '0'){
    		push @{$centileParams}, { centile_param => $row->{centile_param}, timeperiod_id => $row->{timeperiod_id} };
    	}
    }

    return $centileParams;
}

1;
