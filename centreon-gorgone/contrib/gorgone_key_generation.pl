#!/usr/bin/perl
use strict;
use warnings FATAL => 'all';
use Try::Tiny;
use Crypt::PK::RSA;
use File::Basename qw( fileparse );

# generate key if there is none.
# Gorgone can generate it's own key, but as we need the thumbprint in the configuration we need to generate them before launching gorgone.
# this script only create key if the files don't exists, and silently finish if the files already exists.

my ($privkey, $pubkey);

my $priv_dest =  '/var/lib/centreon-gorgone/.keys/rsakey.priv.pem';
my $pub_dest = '/var/lib/centreon-gorgone/.keys/rsakey.pub.pem';
$ARGV[0] and $priv_dest = $ARGV[0];
$ARGV[1] and $pub_dest = $ARGV[1];
if (-f $priv_dest or -f $pub_dest){
    print("files already exist, no overriding is done.\n");
    exit 0;
}
try {
    my $pkrsa = Crypt::PK::RSA->new();
    $pkrsa->generate_key(256, 65537);
    $pubkey  = $pkrsa->export_key_pem('public_x509');
    $privkey = $pkrsa->export_key_pem('private');
} catch {
    die("Cannot generate server keys: $_\n");
};

my ( $priv_key_name, $priv_folder_name ) = fileparse $priv_dest;
`mkdir -p $priv_folder_name`;
open(my $priv_fh, '>', $priv_dest) or die("failed opening $priv_dest : $!");
print $priv_fh $privkey;
print "private key saved to file.\n";

my ( $pub_key_name, $pub_folder_name ) = fileparse $pub_dest;
`mkdir -p $pub_folder_name`;
open(my $pub_fh, '>', $pub_dest) or die("failed opening $pub_dest : $!");
print $pub_fh $pubkey;
print "pub key saved to file.\n";
