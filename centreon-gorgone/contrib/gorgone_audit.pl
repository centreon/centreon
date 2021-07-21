#!/usr/bin/perl

use warnings;
use strict;
use FindBin;
use lib "$FindBin::Bin";
# to be launched from contrib directory
use lib "$FindBin::Bin/../";

gorgone::script::gorgone_audit->new()->run();

package gorgone::script::gorgone_audit;

use strict;
use warnings;
use Data::Dumper;
use gorgone::standard::misc;
use gorgone::class::http::http;
use JSON::XS;

use base qw(gorgone::class::script);

sub new {
    my $class = shift;
    my $self = $class->SUPER::new('gorgone_audit',
        centreon_db_conn => 0,
        centstorage_db_conn => 0,
        noconfig => 0
    );

    bless $self, $class;
    $self->add_options(
        'url:s' => \$self->{url}
    );
    return $self;
}

sub init {
    my $self = shift;
    $self->SUPER::init();

    $self->{url} = 'http://127.0.0.1:8085' if (!defined($self->{url}) || $self->{url} eq '');
    $self->{http} = gorgone::class::http::http->new(logger => $self->{logger});
}

sub json_decode {
    my ($self, %options) = @_;

    my $decoded;
    eval {
        $decoded = JSON::XS->new->utf8->decode($options{content});
    };
    if ($@) {
        $self->{logger}->writeLogError("cannot decode json response: $@");
        exit(1);
    }

    return $decoded;
}

sub schedule_audit {
    my ($self) = @_;

    my ($code, $content) = $self->{http}->request(
        http_backend => 'curl',
        method => 'POST',
        hostname => '',
        full_url => $self->{url} . '/api/centreon/audit/schedule',
        query_form_post => '{}',
        header => [
            'Accept-Type: application/json; charset=utf-8',
            'Content-Type: application/json; charset=utf-8',
        ],
        curl_opt => ['CURLOPT_SSL_VERIFYPEER => 0', 'CURLOPT_POSTREDIR => CURL_REDIR_POST_ALL'],
        warning_status => '',
        unknown_status => '',
        critical_status => ''
    );

    if ($code) {
        $self->{logger}->writeLogError("http request error");
        exit(1);
    }
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

sub get_audit_log {
    my ($self) = @_;
    
    my $progress = 0;
    while (1) {
        my ($code, $content) = $self->{http}->request(
            http_backend => 'curl',
            method => 'GET',
            hostname => '',
            full_url => $self->{url} . '/api/log/' . $self->{token},
            header => [
                'Accept-Type: application/json; charset=utf-8'
            ],
            curl_opt => ['CURLOPT_SSL_VERIFYPEER => 0', 'CURLOPT_POSTREDIR => CURL_REDIR_POST_ALL'],
            warning_status => '',
            unknown_status => '',
            critical_status => ''
        );

        if ($code) {
            $self->{logger}->writeLogError("Login error [code: '" . $self->{http}->get_code() . "'] [message: '" . $self->{http}->get_message() . "']");
            exit(1);
        }
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
            if ($_->{code} == 500 && $progress < $data->{complete}) {
                $self->{logger}->writeLogInfo("audit completed: $data->{complete}\%");
                $progress = $data->{complete};
            } elsif ($_->{code} == 1) {
                $self->{logger}->writeLogError("audit execution: $data->{message}");
                $stop = 1;
            } elsif ($_->{code} == 2) {
                $self->{logger}->writeLogInfo("audit result: " . Data::Dumper::Dumper($data->{audit}));
                $stop = 1;
            }
        }

        last if ($stop == 1);
        sleep(10);
    }
}

sub run {
    my $self = shift;

    $self->SUPER::run();
    $self->schedule_audit();
    $self->get_audit_log();
}

__END__

=head1 NAME

gorgone_audit.pl - script to execute and get audit

=head1 SYNOPSIS

gorgone_audit.pl [options]

=head1 OPTIONS

=over 8

=item B<--url>

Specify the api url (default: 'http://127.0.0.1:8085').

=item B<--severity>

Set the script log severity (default: 'info').

=item B<--help>

Print a brief help message and exits.

=back

=head1 DESCRIPTION

B<gorgone_audit.pl>

=cut

