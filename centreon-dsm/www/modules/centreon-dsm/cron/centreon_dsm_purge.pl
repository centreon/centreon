#!/usr/bin/perl

use warnings;
use strict;

centreon::script::centreon_dsm_purge->new()->run();

package centreon::script::centreon_dsm_purge;

use strict;
use warnings;
use centreon::script;

use base qw(centreon::script);
use vars qw(%centreon_dsm_purge_config);

sub new {
    my $class = shift;
    my $self = $class->SUPER::new("centreon_dsm_purge",
        centreon_db_conn => 0,
        centstorage_db_conn => 1,
    );
    bless $self, $class;

    $self->add_options(
        "config-extra=s" => \$self->{opt_extra},
    );
     %{$self->{centreon_dsm_purge_default_config}} = (
       history_time => 180,
    );

    return $self;
}

sub init {
    my ($self, %options) = @_;
    $self->SUPER::init();

    $self->{logger}->writeLogInfo("centreon_dsm_purge.pl launched");
    if (!defined($self->{opt_extra})) {
        $self->{opt_extra} = "/etc/centreon/centreon_dsm_purge.pm";
    }
    if (-f $self->{opt_extra}) {
        require $self->{opt_extra};
    } else {
        $self->{logger}->writeLogInfo("Can't find extra config file $self->{opt_extra}");
    }
    $self->{dsm_purge_config} = {%{$self->{centreon_dsm_purge_default_config}}, %centreon_dsm_purge_config};

    if (!defined($self->{dsm_purge_config}->{history_time}) || $self->{dsm_purge_config}->{history_time} !~ /\d+/ ||
        $self->{dsm_purge_config}->{history_time} <= 0) {
        $self->{logger}->writeLogError("Please set a postive numeric value for history time");
        exit(1);
    }
}

sub run {
    my ($self, %options) = @_;

    $self->SUPER::run();

    $self->{csdb}->connect();
    my $query = sprintf("DELETE FROM mod_dsm_history WHERE ctime < " . (time() - ($self->{dsm_purge_config}->{history_time} * 86400)));
    my ($status, $sth) = $self->{csdb}->query($query);
    if ($status == -1) {
        $self->{logger}->writeLogError("Cannot write in database");
        exit 1;
    }
}

__END__

=head1 NAME

centreon_dsm_purge.pl - command to clean centreon_dsm_history table

=head1 SYNOPSIS

centreon_dsm_purge.pl [options]

=head1 OPTIONS

=over 8

=item B<--config>

Specify the path to the main configuration file (default: /etc/centreon/conf.pm).

=item B<--help>

Print a brief help message and exits.

=back

=head1 DESCRIPTION

B<centreon_dsm_purge.pl>.

=cut


