#!/usr/bin/perl

use warnings;
use strict;

centreon::script::dsmclient->new()->run();

package centreon::script::dsmclient;

use strict;
use warnings;
use centreon::script;

use base qw(centreon::script);

use vars qw(%dsmclient_config);

sub new {
    my $class = shift;
    my $self = $class->SUPER::new("dsmclient",
        centreon_db_conn => 0, # use my connection because i don't need lock!!
        centstorage_db_conn => 0,
    );
    bless $self, $class;

    $self->add_options(
        "host-id:s"         => \$self->{host_id},
        "Host:s"            => \$self->{host},
        "H:s"               => \$self->{host},
        "id:s"              => \$self->{id},
        "i:s"               => \$self->{id},
        "status:s"          => \$self->{status},
        "s:s"               => \$self->{status},
        "time:s"            => \$self->{time},
        "t:s"               => \$self->{time},
        "output:s"          => \$self->{output},
        "o:s"               => \$self->{output},
        "macro:s"           => \$self->{macro},
        "m:s"               => \$self->{macro},
        "pool-prefix:s"     => \$self->{pool_prefix},
        "config-extra:s"    => \$self->{opt_extra},
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
    if (!defined($self->{status}) || $self->{status} !~ /^[0123]$/) {
        $self->{logger}->writeLogError("Please set --status option with good value");
        exit(1);
    }
    if (!defined($self->{time}) || $self->{time} !~ /\d+/) {
        $self->{time} = time();
    }

    if (!defined($self->{opt_extra})) {
        $self->{opt_extra} = "/etc/centreon/centreon_dsmclient.pm";
    }
    if (-f $self->{opt_extra}) {
        require $self->{opt_extra};
    } else {
        $self->{logger}->writeLogInfo("Can't find extra config file $self->{opt_extra}");
    }
    $self->{centreon_config} = {%{$self->{centreon_config}}, %dsmclient_config};
}

sub get_pool_prefix {
    my ($self, %options) = @_;

    my $query = "SELECT pool_prefix FROM mod_dsm_pool WHERE pool_host_id = " . $self->{db_centreon}->quote($options{host_id}) . " AND pool_activate = '1'";
    if (defined($self->{pool_prefix}) && $self->{pool_prefix} ne '') {
        $query .= "AND pool_prefix = " . $self->{db_centreon}->quote($self->{pool_prefix});
    }
    my ($status, $sth) = $self->{db_centreon}->query($query);
    if ($status == -1) {
        $self->{logger}->writeLogError("Cannot get pool prefix from database");
        exit 1;
    }

    if ((my $row = $sth->fetchrow_hashref())) {
        $self->{logger}->writeLogInfo("find pool '" . $row->{pool_prefix}  . "' for host id '" . $options{host_id} . "'");
        return $row->{pool_prefix};
    }

    $self->{logger}->writeLogError("Cannot find host id or pool prefix");
    exit(1);
}

sub get_hosts {
    my ($self, %options) = @_;

    my $host_id = $self->{host_id};
    if (defined($self->{host}) && $self->{host} ne '') {
        my $query = "SELECT host_id FROM host WHERE host_name = " . $self->{db_centreon}->quote($self->{host}) . " OR host_address = " . $self->{db_centreon}->quote($self->{host});
        my ($status, $sth) = $self->{db_centreon}->query($query);
        if ($status == -1) {
            $self->{logger}->writeLogError("Cannot get host ID from database");
            exit 1;
        }
        my $i = 0;
        while ((my $row = $sth->fetchrow_hashref())) {
            $host_id = $row->{host_id};
            $i++;
        }
        if ($i == 0) {
            $self->{logger}->writeLogError("Cannot find host id for host '" . $self->{host} . "'");
            exit(1);
        }
        if ($i > 1) {
            $self->{logger}->writeLogError("Find too many hosts ($i) for host '" . $self->{host} . "'. Need only one!");
            exit(1);
        }
    } elsif (!defined($self->{host_id}) || $self->{host_id} eq '') {
        $self->{logger}->writeLogError("Please set --host-id option");
        exit(1);
    }

    return $host_id;
}

sub run {
    my ($self, %options) = @_;

    $self->SUPER::run();

    $self->{db_centreon} = centreon::common::db->new(db => $self->{centreon_config}->{centreon_db},
                                                     host => $self->{centreon_config}->{db_host},
                                                     port => $self->{centreon_config}->{db_port},
                                                     user => $self->{centreon_config}->{db_user},
                                                     password => $self->{centreon_config}->{db_passwd},
                                                     force => 1,
                                                     logger => $self->{logger});
    $self->{db_centreon}->connect();

    $self->{db_centstorage} = centreon::common::db->new(
        db => $self->{centreon_config}->{centstorage_db},
        host => $self->{centreon_config}->{db_host},
        port => $self->{centreon_config}->{db_port},
        user => $self->{centreon_config}->{db_user},
        password => $self->{centreon_config}->{db_passwd},
        force => 1,
        logger => $self->{logger}
    );
    $self->{db_centstorage}->connect();

    my $host_id = $self->get_hosts();
    my $pool_prefix = $self->get_pool_prefix(host_id => $host_id);
    my $query = sprintf("INSERT INTO mod_dsm_cache (`host_id`, `ctime`, `status`, `pool_prefix`, `id`, `macros`, `output`) VALUES (%s, %s, %s, %s, %s, %s, %s)",
        $self->{db_centstorage}->quote($host_id), $self->{db_centstorage}->quote($self->{time}), $self->{db_centstorage}->quote($self->{status}),
        $self->{db_centstorage}->quote($pool_prefix), $self->{db_centstorage}->quote($self->{id}), $self->{db_centstorage}->quote($self->{macro}),
        $self->{db_centstorage}->quote($self->{output}));
    my ($status, $sth) = $self->{db_centstorage}->query($query);
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

=item B<--config-extra>

Specify the extra configuration file (default: /etc/centreon/centreon_dsmclient.pm).
Can be used to overload database configuration.

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


