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

package gorgone::modules::centreon::mbi::etlworkers::import::main;

use strict;
use warnings;
use gorgone::standard::misc;
use File::Basename;

sub sql {
    my ($etlwk, %options) = @_;

    return if (!defined($options{params}->{sql}));

    foreach (@{$options{params}->{sql}}) {
        $etlwk->{messages}->writeLog('INFO', $_->[0]);
        if ($options{params}->{db} eq 'centstorage') {
            $etlwk->{dbbi_centstorage_con}->query($_->[1]);
        } elsif ($options{params}->{db} eq 'centreon') {
            $etlwk->{dbbi_centreon_con}->query($_->[1]);
        }
    }
}

sub command {
    my ($etlwk, %options) = @_;

    return if (!defined($options{params}->{command}) || $options{params}->{command} eq '');

    my ($error, $stdout, $return_code) = gorgone::standard::misc::backtick(
        command => $options{params}->{command},
        timeout => 7200,
        wait_exit => 1,
        redirect_stderr => 1,
        logger => $options{logger}
    );

    if ($error != 0) {
        die $options{params}->{message} . ": execution failed: $stdout";
    }

    $etlwk->{messages}->writeLog('INFO', $options{params}->{message});
    $etlwk->{logger}->writeLogDebug("[mbi-etlworkers] succeeded command (code: $return_code): $stdout");
}

sub load {
    my ($etlwk, %options) = @_;

    return if (!defined($options{params}->{file}));

    my ($file, $dir) = File::Basename::fileparse($options{params}->{file});

    if (! -d "$dir" && ! -w "$dir") {
        $etlwk->{messages}->writeLog('ERROR', "Cannot write into directory " . $dir);
    }

    command($etlwk, params => { command => $options{params}->{dump}, message => $options{params}->{message} });

    if ($options{params}->{db} eq 'centstorage') {
        $etlwk->{dbbi_centstorage_con}->query($options{params}->{load});
    } elsif ($options{params}->{db} eq 'centreon') {
        $etlwk->{dbbi_centreon_con}->query($options{params}->{load});
    }

    unlink($options{params}->{file});
}

1;
