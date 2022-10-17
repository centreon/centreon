#
# Copyright 2022 Centreon (http://www.centreon.com/)
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

package apps::video::zixi::restapi::plugin;

use strict;
use warnings;
use base qw(centreon::plugins::script_custom);

sub new {
    my ($class, %options) = @_;
    my $self = $class->SUPER::new(package => __PACKAGE__, %options);
    bless $self, $class;

    $self->{version} = '1.0';
    %{$self->{modes}} = (
        'broadcaster-input-usage'      => 'apps::video::zixi::restapi::mode::broadcasterinputusage',
        'broadcaster-license-usage'    => 'apps::video::zixi::restapi::mode::broadcasterlicenseusage',
        'broadcaster-output-usage'     => 'apps::video::zixi::restapi::mode::broadcasteroutputusage',
        'broadcaster-system-usage'     => 'apps::video::zixi::restapi::mode::broadcastersystemusage',
        'feeder-input-usage'           => 'apps::video::zixi::restapi::mode::feederinputusage',
        'feeder-output-usage'          => 'apps::video::zixi::restapi::mode::feederoutputusage',
    );

    $self->{custom_modes}{api} = 'apps::video::zixi::restapi::custom::api';
    return $self;
}

1;

__END__

=head1 PLUGIN DESCRIPTION

Check Zixi through HTTP/REST API.

=cut
