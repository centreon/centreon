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

package cloud::kubernetes::mode::daemonsetstatus;

use base qw(centreon::plugins::templates::counter);

use strict;
use warnings;
use centreon::plugins::templates::catalog_functions qw(catalog_status_threshold_ng);

sub custom_status_perfdata {
    my ($self, %options) = @_;
    
    $self->{output}->perfdata_add(
        nlabel => 'daemonset.pods.desired.count',
        value => $self->{result_values}->{desired},
        instances => $self->{result_values}->{name}
    );
    $self->{output}->perfdata_add(
        nlabel => 'daemonset.pods.current.count',
        value => $self->{result_values}->{current},
        instances => $self->{result_values}->{name}
    );
    $self->{output}->perfdata_add(
        nlabel => 'daemonset.pods.available.count',
        value => $self->{result_values}->{available},
        instances => $self->{result_values}->{name}
    );
    $self->{output}->perfdata_add(
        label => 'up_to_date',
        nlabel => 'daemonset.pods.uptodate.count',
        value => $self->{result_values}->{up_to_date},
        instances => $self->{result_values}->{name}
    );
    $self->{output}->perfdata_add(
        nlabel => 'daemonset.pods.ready.count',
        value => $self->{result_values}->{ready},
        instances => $self->{result_values}->{name}
    );
    $self->{output}->perfdata_add(
        nlabel => 'daemonset.pods.misscheduled.count',
        value => $self->{result_values}->{misscheduled},
        instances => $self->{result_values}->{name}
    );
}

sub custom_status_output {
    my ($self, %options) = @_;

    return sprintf(
        "Pods Desired: %s, Current: %s, Available: %s, Up-to-date: %s, Ready: %s, Misscheduled: %s",
        $self->{result_values}->{desired},
        $self->{result_values}->{current},
        $self->{result_values}->{available},
        $self->{result_values}->{up_to_date},
        $self->{result_values}->{ready},
        $self->{result_values}->{misscheduled}
    );
}

sub prefix_daemonset_output {
    my ($self, %options) = @_;

    return "DaemonSet '" . $options{instance_value}->{name} . "' ";
}

sub set_counters {
    my ($self, %options) = @_;
    
    $self->{maps_counters_type} = [
        { name => 'daemonsets', type => 1, cb_prefix_output => 'prefix_daemonset_output',
            message_multiple => 'All DaemonSets status are ok', skipped_code => { -11 => 1 } }
    ];

    $self->{maps_counters}->{daemonsets} = [
        {
            label => 'status',
            type => 2,
            warning_default => '%{up_to_date} < %{desired}',
            critical_default => '%{available} < %{desired}',
            set => {
                key_values => [ { name => 'desired' }, { name => 'current' }, { name => 'up_to_date' },
                    { name => 'available' }, { name => 'ready' }, { name => 'misscheduled' }, { name => 'name' },
                    { name => 'namespace' } ],
                closure_custom_output => $self->can('custom_status_output'),
                closure_custom_perfdata => $self->can('custom_status_perfdata'),
                closure_custom_threshold_check => \&catalog_status_threshold_ng
            }
        }
    ];
}

sub new {
    my ($class, %options) = @_;
    my $self = $class->SUPER::new(package => __PACKAGE__, %options, force_new_perfdata => 1);
    bless $self, $class;
    
    $options{options}->add_options(arguments => {
        'filter-name:s'      => { name => 'filter_name' },
        'filter-namespace:s' => { name => 'filter_namespace' }
    });
   
    return $self;
}

sub manage_selection {
    my ($self, %options) = @_;

    $self->{daemonsets} = {};

    my $results = $options{custom}->kubernetes_list_daemonsets();
    
    foreach my $daemonset (@{$results}) {
        if (defined($self->{option_results}->{filter_name}) && $self->{option_results}->{filter_name} ne '' &&
            $daemonset->{metadata}->{name} !~ /$self->{option_results}->{filter_name}/) {
            $self->{output}->output_add(long_msg => "skipping '" . $daemonset->{metadata}->{name} . "': no matching filter name.", debug => 1);
            next;
        }
        if (defined($self->{option_results}->{filter_namespace}) && $self->{option_results}->{filter_namespace} ne '' &&
            $daemonset->{metadata}->{namespace} !~ /$self->{option_results}->{filter_namespace}/) {
            $self->{output}->output_add(long_msg => "skipping '" . $daemonset->{metadata}->{namespace} . "': no matching filter namespace.", debug => 1);
            next;
        }

        $self->{daemonsets}->{ $daemonset->{metadata}->{uid} } = {
            name => $daemonset->{metadata}->{name},
            namespace => $daemonset->{metadata}->{namespace}
        };

        $self->{daemonsets}->{ $daemonset->{metadata}->{uid} }->{desired} =
            defined($daemonset->{status}->{desiredNumberScheduled}) && $daemonset->{status}->{desiredNumberScheduled} =~ /(\d+)/ ? $1 : 0;
        $self->{daemonsets}->{ $daemonset->{metadata}->{uid} }->{current} =
            defined($daemonset->{status}->{currentNumberScheduled}) && $daemonset->{status}->{currentNumberScheduled} =~ /(\d+)/ ? $1 : 0;
        $self->{daemonsets}->{ $daemonset->{metadata}->{uid} }->{up_to_date} =
            defined($daemonset->{status}->{updatedNumberScheduled}) && $daemonset->{status}->{updatedNumberScheduled} =~ /(\d+)/ ? $1 : 0;
        $self->{daemonsets}->{ $daemonset->{metadata}->{uid} }->{available} =
            defined($daemonset->{status}->{numberAvailable}) && $daemonset->{status}->{numberAvailable} =~ /(\d+)/ ? $1 : 0;
        $self->{daemonsets}->{ $daemonset->{metadata}->{uid} }->{ready} =
            defined($daemonset->{status}->{numberReady}) && $daemonset->{status}->{numberReady} =~ /(\d+)/ ? $1 : 0;
        $self->{daemonsets}->{ $daemonset->{metadata}->{uid} }->{misscheduled} =
            defined($daemonset->{status}->{numberMisscheduled}) && $daemonset->{status}->{numberMisscheduled} =~ /(\d+)/ ? $1 : 0;
    }
    
    if (scalar(keys %{$self->{daemonsets}}) <= 0) {
        $self->{output}->add_option_msg(short_msg => "No DaemonSets found.");
        $self->{output}->option_exit();
    }
}

1;

__END__

=head1 MODE

Check DaemonSet status.

=over 8

=item B<--filter-name>

Filter DaemonSet name (can be a regexp).

=item B<--filter-namespace>

Filter DaemonSet namespace (can be a regexp).

=item B<--warning-status>

Set warning threshold for status (Default: '%{up_to_date} < %{desired}')
Can used special variables like: %{name}, %{namespace}, %{desired}, %{current},
%{available}, %{unavailable}, %{up_to_date}, %{ready}, %{misscheduled}.

=item B<--critical-status>

Set critical threshold for status (Default: '%{available} < %{desired}').
Can used special variables like: %{name}, %{namespace}, %{desired}, %{current},
%{available}, %{unavailable}, %{up_to_date}, %{ready}, %{misscheduled}.

=back

=cut
