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

package apps::apcupsd::local::mode::libgetdata;

use base qw(centreon::plugins::mode);

use strict;
use warnings;
use centreon::plugins::misc;

sub getdata {
    my ($self, %options) = @_;

    my $stdout = centreon::plugins::misc::execute(output => $self->{output},
                                                  options => $self->{option_results},
                                                  sudo => $self->{option_results}->{sudo},
                                                  command => $self->{option_results}->{command},
                                                  command_path => $self->{option_results}->{command_path},
                                                  command_options => $self->{option_results}->{command_options} . $self->{option_results}->{apchost} . ":" . $self->{option_results}->{apcport} . $self->{option_results}->{command_options2});

    my $searchpattern = $self->{option_results}->{searchpattern};
    my ($valueok);
    my ($value);
    #print $stdout;
    foreach (split(/\n/, $stdout)) {
        if (/^$searchpattern\s*:\s*(.*)\s(Percent Load Capacity|Percent|Minutes|Seconds|Volts|Hz|seconds|C Internal|F Internal|C|F)/i) {
            $valueok = "1";
            $value = $1;
            #print $value;
            #print "\n";
        };
    };

    if ($valueok == "1") {
        #print $value;
        return $value;
    } else {
        $self->{output}->output_add(severity => 'CRITICAL',
                                    short_msg => 'NO DATA FOUND');     
        $self->{output}->display();
        $self->{output}->exit();
    };
};

1;
