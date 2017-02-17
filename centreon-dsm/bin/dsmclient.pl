#!/usr/bin/perl

use warnings;
use strict;

centreon::script::dsmclient->new()->run();

package centreon::script::dsmclient;

use strict;
use warnings;
use centreon::script;

use base qw(centreon::script);

sub new {
    my $class = shift;
    my $self = $class->SUPER::new("dsmclient",
        centreon_db_conn => 1,
        centstorage_db_conn => 1,
    );
    bless $self, $class;

    $self->add_options(
        "host-id:s"     => \$self->{host_id},
        "id:s"          => \$self->{id},
        "status:s"      => \$self->{status},
        "time:s"        => \$self->{time},
        "output:s"      => \$self->{output},
        "macro:s"       => \$self->{macro},
        "pool-prefix:s" => \$self->{pool_prefix},
    );
    
    return $self;
}

sub init {
    my ($self, %options) = @_;
    $self->SUPER::init();

    $self->{logger}->writeLogInfo(
        sprintf("client launched with options [host id = %s] [id = %s] [status = %s] [time = %s] [output = %s] [macro = %s] [pool prefix = %s]",
            defined($self->{host_id}) ? $self->{host_id} : '-', defined($self->{id}) ? $self->{id} : '-', 
            defined($self->{status}) ? $self->{status} : '-', defined($self->{time}) ? $self->{time} : '-', 
            defined($self->{output}) ? $self->{output} : '-', defined($self->{macro}) ? $self->{macro} : '-', 
            defined($self->{pool_prefix}) ? $self->{pool_prefix} : '-')
    );
    if (!defined($self->{host_id}) || $self->{host_id} eq '') {
        $self->{logger}->writeLogError("Please set --host-id option");
        exit(1);
    }
    if (!defined($self->{status}) || $self->{status} !~ /^[0123]$/) {
        $self->{logger}->writeLogError("Please set --status option with good value");
        exit(1);
    }
    if (!defined($self->{time}) || $self->{time} !~ /\d+/) {
        $self->{time} = time();
    }
}

sub get_pool_prefix {
    my ($self, %options) = @_;
    
    my $query = "SELECT pool_prefix FROM mod_dsm_pool WHERE pool_host_id = " . $self->{cdb}->quote($self->{host_id}) . " AND pool_activate = '1'";
    if (defined($self->{pool_prefix}) && $self->{pool_prefix} ne '') {
        $query .= "AND pool_prefix = " . $self->{cdb}->quote($self->{pool_prefix});
    }
    my ($status, $sth) = $self->{cdb}->query($query);
    if ($status == -1) {
        $self->{logger}->writeLogError("Cannot get pool prefix from database");
        exit 1;
    }
    
    if ((my $row = $sth->fetchrow_hashref())) {
        $self->{logger}->writeLogInfo("find pool '" . $row->{pool_prefix}  . "' for host id '" . $self->{host_id} . "'");
        return $row->{pool_prefix};
    }
    
    $self->{logger}->writeLogError("Cannot find host id or pool prefix");
    exit(1);
}

sub run {
    my ($self, %options) = @_;

    $self->SUPER::run();
   
    my $pool_prefix = $self->get_pool_prefix();
    $self->{csdb}->connect();
    my $query = sprintf("INSERT INTO mod_dsm_cache (`host_id`, `ctime`, `status`, `pool_prefix`, `id`, `macros`, `output`) VALUES (%s, %s, %s, %s, %s, %s, %s)",
        $self->{csdb}->quote($self->{host_id}), $self->{csdb}->quote($self->{ctime}), $self->{csdb}->quote($self->{status}),
        $self->{csdb}->quote($pool_prefix), $self->{csdb}->quote($self->{id}), $self->{csdb}->quote($self->{macros}),
        $self->{csdb}->quote($self->{output}));
    my ($status, $sth) = $self->{csdb}->query($query);
    if ($status == -1) {
        $self->{logger}->writeLogError("Cannot write in database");
        exit 1;
    }
}

__END__

=head1 NAME

dsmclient.pl - command to send event to dsmd daemon

=head1 SYNOPSIS

dsmclient.pl [options]

=head1 OPTIONS

=over 8

=item B<--config>

Specify the path to the main configuration file (default: /etc/centreon/conf.pm).

=item B<--help>

Print a brief help message and exits.

=item B<--host-id>

Host ID of the server with slots.

=item B<--id>

ID of the alarm (useful if you want to set value in the same slot).

=item B<--status>

Status of the slot (0 = OK, 1 = WARNING, 2 = CRITICAL, 3 = UNKNOWN).

=item B<--time>

Time of the event. If not set, current time is used.

=item B<--output>

Output displayed in the slot.

=item B<--macro>

Extra custom to update (Example: 'macro1=value1|macro2=value2|macro3=value3').

=item B<--pool-prefix>

Slots to used for the host. If not set, we used the first pool of slots.

=back

=head1 DESCRIPTION

B<dsmclient.pl>.

=cut


