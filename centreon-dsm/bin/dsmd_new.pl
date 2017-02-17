#!/usr/bin/perl

use warnings;
use strict;

centreon::script::dsmd->new()->run();

package centreon::script::dsmd;

use strict;
use warnings;
use centreon::script;

use base qw(centreon::script);

my %handlers = (TERM => {}, DIE => {}, HUP => {});

sub new {
    my $class = shift;
    my $self = $class->SUPER::new("dsmd",
        centreon_db_conn => 0,
        centstorage_db_conn => 0,
    );
    bless $self, $class;

    $self->add_options(
    );
    
    $self->{reload} = 0;
    $self->set_signal_handlers();
    return $self;
}

sub set_signal_handlers {
    my $self = shift;

    $SIG{TERM} = \&class_handle_TERM;
    $handlers{TERM}->{$self} = sub { $self->handle_TERM() };
    $SIG{__DIE__} = \&class_handle_DIE;
    $handlers{DIE}->{$self} = sub { $self->handle_DIE($_[0]) };
    $SIG{HUP} = \&class_handle_HUP;
    $handlers{HUP}->{$self} = sub { $self->handle_HUP() };
}

sub class_handle_TERM {
    foreach (keys %{$handlers{TERM}}) {
        &{$handlers{TERM}->{$_}}();
    }
    exit(0);
}

sub class_handle_DIE {
    my ($msg) = @_;

    foreach (keys %{$handlers{DIE}}) {
        &{$handlers{DIE}->{$_}}($msg);
    }
}

sub class_handle_HUP {
    foreach (keys %{$handlers{HUP}}) {
        &{$handlers{HUP}->{$_}}();
    }
}

sub handle_DIE {
    my ($self, $msg) = @_;
    my $msg = shift;

    $self->{logger}->writeLogError($msg);    
    exit(1);
}

sub handle_TERM {
    my $self = shift;
    $self->{logger}->writeLogInfo("$$ Receiving order to stop...");
    exit(0);
}

sub handle_HUP {
    my $self = shift;
    $self->{reload} = 1;
}

sub init {
    my ($self, %options) = @_;
    $self->SUPER::init();

    $self->{logger}->writeLogInfo(
        sprintf("server launched",
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

sub run {
    my ($self, %options) = @_;

    $self->SUPER::run();
    
    my $centreon_db_centstorage = centreon::common::db->new(db => $self->{centreon_config}->{centstorage_db},
                                                            host => $self->{centreon_config}->{db_host},
                                                            port => $self->{centreon_config}->{db_port},
                                                            user => $self->{centreon_config}->{db_user},
                                                            password => $self->{centreon_config}->{db_passwd},
                                                            force => 0,
                                                            logger => $self->{logger});
    $centreon_db_centstorage->connect();
   
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

dsmd.pl - centreon dsm daemon to manage events

=head1 SYNOPSIS

dsmd.pl [options]

=head1 OPTIONS

=over 8

=item B<--config>

Specify the path to the main configuration file (default: /etc/centreon/conf.pm).

=item B<--help>

Print a brief help message and exits.

=back

=head1 DESCRIPTION

B<dsmd.pl>.

=cut


