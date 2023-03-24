#!/usr/bin/perl

use warnings;
use strict;
use FindBin;
use lib "$FindBin::Bin";
# to be launched from contrib directory
use lib "$FindBin::Bin/../";

gorgone::script::perfdataStatisticsBuilder->new()->run();

package gorgone::script::perfdataStatisticsBuilder;

use strict;
use warnings;
use Data::Dumper;
use gorgone::modules::centreon::mbi::libs::Utils;
use gorgone::standard::misc;
use gorgone::class::http::http;
use JSON::XS;

use base qw(gorgone::class::script);

sub new {
    my $class = shift;
    my $self = $class->SUPER::new(
        'perfdataStatisticsBuilder',
        centreon_db_conn => 0,
        centstorage_db_conn => 0,
        noconfig => 0
    );

    bless $self, $class;
    
    $self->{moptions}->{rebuild} = 0;
    $self->{moptions}->{daily} = 0;
    $self->{moptions}->{import} = 0;
    $self->{moptions}->{dimensions} = 0;
    $self->{moptions}->{event} = 0;
    $self->{moptions}->{perfdata} = 1;
    $self->{moptions}->{start} = '';
    $self->{moptions}->{end} = '';
    $self->{moptions}->{nopurge} = 0;
    $self->{moptions}->{month_only} = 0;
    $self->{moptions}->{centile_only} = 0;
    $self->{moptions}->{no_centile} = 0;

    $self->add_options(
        'url:s'        => \$self->{url},
        'r|rebuild'    => \$self->{moptions}->{rebuild},
        'd|daily'      => \$self->{moptions}->{daily},
        's:s'          => \$self->{moptions}->{start},
        'e:s'          => \$self->{moptions}->{end},
        'month-only'   => \$self->{moptions}->{month_only},
        'centile-only' => \$self->{moptions}->{centile_only},
        'no-centile'   => \$self->{moptions}->{no_centile},
        'no-purge'     => \$self->{moptions}->{nopurge}
    );
    return $self;
}

sub init {
    my $self = shift;
    $self->SUPER::init();

    $self->{url} = 'http://127.0.0.1:8085' if (!defined($self->{url}) || $self->{url} eq '');
    $self->{http} = gorgone::class::http::http->new(logger => $self->{logger});
    my $utils = gorgone::modules::centreon::mbi::libs::Utils->new($self->{logger});
    if ($utils->checkBasicOptions($self->{moptions}) == 1) {
        exit(1);
    }
}

sub json_decode {
    my ($self, %options) = @_;

    my $decoded;
    eval {
        $decoded = JSON::XS->new->decode($options{content});
    };
    if ($@) {
        $self->{logger}->writeLogError("cannot decode json response: $@");
        exit(1);
    }

    return $decoded;
}

sub run_etl {
    my ($self) = @_;

    my ($code, $content) = $self->{http}->request(
        http_backend => 'curl',
        method => 'POST',
        hostname => '',
        full_url => $self->{url} . '/api/centreon/mbietl/run',
        query_form_post => JSON::XS->new->encode($self->{moptions}),
        header => [
            'Accept-Type: application/json; charset=utf-8',
            'Content-Type: application/json; charset=utf-8',
        ],
        curl_opt => ['CURLOPT_SSL_VERIFYPEER => 0', 'CURLOPT_POSTREDIR => CURL_REDIR_POST_ALL'],
        warning_status => '',
        unknown_status => '',
        critical_status => ''
    );

    if ($self->{http}->get_code() < 200 || $self->{http}->get_code() >= 300) {
        $self->{logger}->writeLogError("Login error [code: '" . $self->{http}->get_code() . "'] [message: '" . $self->{http}->get_message() . "']");
        exit(1);
    }

    my $decoded = $self->json_decode(content => $content);
    if (!defined($decoded->{token})) {
        $self->{logger}->writeLogError('cannot get token');
        exit(1);
    }

    $self->{token} = $decoded->{token};
}

sub display_messages {
    my ($self, %options) = @_;

    if (defined($options{data}->{messages})) {
        foreach (@{$options{data}->{messages}}) {
            if ($_->[0] eq 'D') {
                $self->{logger}->writeLogDebug($_->[1])
            } elsif ($_->[0] eq 'I') {
                $self->{logger}->writeLogInfo($_->[1]);
            } elsif ($_->[0] eq 'E') {
                $self->{logger}->writeLogError($_->[1]);
            }
        }
    }
}

sub get_etl_log {
    my ($self) = @_;

    my $log_id;
    while (1) {
        my $get_param = [];
        if (defined($log_id)) {
            $get_param = ['id=' . $log_id];
        }

        my ($code, $content) = $self->{http}->request(
            http_backend => 'curl',
            method => 'GET',
            hostname => '',
            full_url => $self->{url} . '/api/log/' . $self->{token},
            get_param => $get_param,
            header => [
                'Accept-Type: application/json; charset=utf-8'
            ],
            curl_opt => ['CURLOPT_SSL_VERIFYPEER => 0', 'CURLOPT_POSTREDIR => CURL_REDIR_POST_ALL'],
            warning_status => '',
            unknown_status => '',
            critical_status => ''
        );

        if ($self->{http}->get_code() < 200 || $self->{http}->get_code() >= 300) {
            $self->{logger}->writeLogError("Login error [code: '" . $self->{http}->get_code() . "'] [message: '" . $self->{http}->get_message() . "']");
            exit(1);
        }

        my $decoded = $self->json_decode(content => $content);
        if (!defined($decoded->{data})) {
            $self->{logger}->writeLogError("Cannot get log information");
            exit(1);
        }

        my $stop = 0;
        foreach (@{$decoded->{data}}) {
            my $data = $self->json_decode(content => $_->{data});
            next if (defined($log_id) && $log_id >= $_->{id});
            $log_id = $_->{id};

            if ($_->{code} == 600) {
                $self->display_messages(data => $data);
            } elsif ($_->{code} == 1) {
                $self->display_messages(data => $data);                
                $stop = 1;
            } elsif ($_->{code} == 2) {
                $self->display_messages(data => $data);
                $stop = 1;
            }
        }

        last if ($stop == 1);
        sleep(2);
    }
}

sub run {
    my $self = shift;

    $self->SUPER::run();
    $self->run_etl();
    $self->get_etl_log();
}

__END__

=head1 NAME

perfdataStatisticsBuilder.pl - script to calculate perfdata statistics

=head1 SYNOPSIS

perfdataStatisticsBuilder.pl [options]

=head1 OPTIONS

=over 8

=item B<--url>

Specify the api url (default: 'http://127.0.0.1:8085').

=item B<--severity>

Set the script log severity (default: 'info').

=item B<--help>

Print a brief help message and exits.

=back

    Rebuild options:
        [-r | --rebuild] [-s|--start] <YYYY-MM-DD> [-e|--end] <YYYY-MM-DD> [--no-purge] [--month-only] [--centile-only] [--no-centile]
    Daily run options:
        [-d | --daily]

=head1 DESCRIPTION

B<perfdataStatisticsBuilder.pl>

=cut
