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

package apps::protocols::udp::mode::connection;

use base qw(centreon::plugins::mode);

use strict;
use warnings;
use IO::Socket::INET;
use IO::Select;

sub new {
    my ($class, %options) = @_;
    my $self = $class->SUPER::new(package => __PACKAGE__, %options);
    bless $self, $class;

    $options{options}->add_options(arguments => {
        'hostname:s' => { name => 'hostname' },
        'port:s'     => { name => 'port' },
        'timeout:s'  => { name => 'timeout', default => '3' }
    });

    return $self;
}

sub check_options {
    my ($self, %options) = @_;
    $self->SUPER::init(%options);

    if (($self->{perfdata}->threshold_validate(label => 'warning', value => $self->{option_results}->{warning})) == 0) {
        $self->{output}->add_option_msg(short_msg => "Wrong warning threshold '" . $self->{option_results}->{warning} . "'.");
        $self->{output}->option_exit();
    }
    if (($self->{perfdata}->threshold_validate(label => 'critical', value => $self->{option_results}->{critical})) == 0) {
        $self->{output}->add_option_msg(short_msg => "Wrong critical threshold '" . $self->{option_results}->{critical} . "'.");
        $self->{output}->option_exit();
    }
    if (!defined($self->{option_results}->{hostname})) {
        $self->{output}->add_option_msg(short_msg => "Need to specify '--hostname' option");
        $self->{output}->option_exit();
    }

    if (!defined($self->{option_results}->{port}) || $self->{option_results}->{port} eq '') {
        $self->{output}->add_option_msg(short_msg => "Need to specify '--port' option");
        $self->{output}->option_exit();
    }
    $self->{option_results}->{port} = $self->{option_results}->{port} =~ /(\d+)/ ? $1 : -1;
    if ($self->{option_results}->{port} < 0 || $self->{option_results}->{port} > 65535) {
        $self->{output}->add_option_msg(short_msg => 'Illegal port number (allowed range: 1-65535)');
        $self->{output}->option_exit();
    }
}

sub run {
    my ($self, %options) = @_;

    my $icmp_sock = new IO::Socket::INET(Proto => "icmp");
    if (!defined($icmp_sock)) {
        $self->{output}->add_option_msg(short_msg => "Cannot create socket: $!");
        $self->{output}->option_exit();
    }
    my $read_set = new IO::Select();
    $read_set->add($icmp_sock);

    my $sock = IO::Socket::INET->new(
        PeerAddr => $self->{option_results}->{hostname},
        PeerPort => $self->{option_results}->{port},
        Proto => 'udp',
    );

    $sock->send("Hello");
    close($sock);

    my ($new_readable) = IO::Select->select($read_set, undef, undef, $self->{option_results}->{timeout});
    my $icmp_arrived = 0;
    foreach $sock (@$new_readable) {
        if ($sock == $icmp_sock) {
            $icmp_arrived = 1;
            $icmp_sock->recv(my $buffer,50,0);
        }
    }
    close($icmp_sock);

    if ($icmp_arrived == 1) {
        $self->{output}->output_add(
            severity => 'CRITICAL',
            short_msg => sprintf("Connection failed on port %s", $self->{option_results}->{port})
        );
    } else {
        $self->{output}->output_add(
            severity => 'OK',
            short_msg => sprintf("Connection success on port %s", $self->{option_results}->{port})
        );
    }
    
    $self->{output}->display();
    $self->{output}->exit();
}

1;

__END__

=head1 MODE

Check UDP connection

=over 8

=item B<--hostname>

IP Addr/FQDN of the host

=item B<--port>

Port used

=item B<--timeout>

Connection timeout in seconds (Default: 3)

=back

=cut
