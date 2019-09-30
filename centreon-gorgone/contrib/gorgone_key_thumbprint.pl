#!/usr/bin/perl

use warnings;
use strict;
use FindBin;
use lib "$FindBin::Bin";
# to be launched from contrib directory
use lib "$FindBin::Bin/../";

gorgone::script::gorgone_key_thumbprint->new()->run();

package gorgone::script::gorgone_key_thumbprint;

use strict;
use warnings;
use gorgone::standard::misc;
use Crypt::PK::RSA;

use base qw(gorgone::class::script);

sub new {
    my $class = shift;
    my $self = $class->SUPER::new("gorgone_key_thumbprint",
        centreon_db_conn => 0,
        centstorage_db_conn => 0,
        noconfig => 0
    );

    bless $self, $class;
    $self->add_options(
        'key-path:s' => \$self->{key_path},
    );
    return $self;
}

sub init {
    my $self = shift;
    $self->SUPER::init();

    $self->{key_path} = '/etc/pki/gorgone/pubkey.pem' if (!defined($self->{key_path}) || $self->{key_path} eq '');
}

sub read_key {
    my ($self, $key_path) = @_;

    my $fh;
    if (!open($fh, '<', $key_path)) {
        $self->{logger}->writeLogError("Couldn't open file '$key_path': $!");
        exit(1);
    }
    my $content = do { local $/; <$fh> };
    close($fh);

    return $content;
}

sub get_key_thumbprint {
    my ($self, $key_string) = @_;

    my $kh;
    $key_string =~ s/\\n/\n/g;
    eval {
        $kh = Crypt::PK::RSA->new(\$key_string);
    };
    if ($@) {
        $self->{logger}->writeLogError("Cannot load key: $@");
        return -1;
    }

    return $kh->export_key_jwk_thumbprint('SHA256');
}

sub run {
    my $self = shift;

    $self->SUPER::run();
    my $key = $self->read_key($self->{key_path});
    my $thumbprint = $self->get_key_thumbprint($key);

    $self->{logger}->writeLogInfo("File '$self->{key_path}' JWK thumbprint: " . $thumbprint);
}

__END__

=head1 NAME

gorgone_key_thumbprint.pl - script to get the JWK thumbprint of a RSA key.

=head1 SYNOPSIS

gorgone_key_thumbprint.pl [options]

=head1 OPTIONS

=over 8

=item B<--key-path>

Specify the path to the RSA key (default: '/etc/pki/gorgone/pubkey.pem').

=item B<--severity>

Set the script log severity (default: 'error').

=item B<--help>

Print a brief help message and exits.

=back

=head1 DESCRIPTION

B<gorgone_key_thumbprint.pl>

=cut

