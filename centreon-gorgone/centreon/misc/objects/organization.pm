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

package centreon::misc::objects::organization;

use strict;
use warnings;

use base qw(centreon::misc::objects::object);

sub new {
    my ($class, %options) = @_;
    my $self = $class->SUPER::new(%options);
    
    bless $self, $class;
    return $self;
}

sub get_organizations {
    my ($self, %options) = @_;

    my %defaults = (request => 'SELECT', tables => ['cfg_organizations'], fields => ['*'], where => "active = '1'");
    my $options_builder = {%defaults, %options};
    return $self->execute(%$options_builder);    
}

1;
