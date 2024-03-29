#!/usr/bin/perl
#!/usr/bin/perl
use strict;
use warnings;
use v5.10;

$| = 1; # autoflush stdout after each print

use ZMQ::FFI qw(ZMQ_DEALER);
use ZMQ::FFI::Constants qw(ZMQ_PULL ZMQ_PUSH ZMQ_SUB ZMQ_DONTWAIT ZMQ_SNDMORE ZMQ_RCVHWM ZMQ_SNDHWM);

my $while_counter = 0;
my $ae_counter    = 0;

my $mode = 0;
print "mode : $mode\n";
# creating the context
my $context = ZMQ::FFI->new();
# create a new socket in dealer mode.
my $receiver = $context->socket(ZMQ_DEALER);

$receiver->set_identity("test-identity");
$receiver->die_on_error(1);
my $ipcLocation = 'ipc:///tmp/dealer-test1';
my $err         = $receiver->connect($ipcLocation);
print "Connecting to ipc $ipcLocation, err is : $err";

$receiver->send_multipart(['this is a message to set the identity in the router']);

until ($receiver->has_pollin) {
    sleep 1;
    print "ha we have something";
}
while ($receiver->has_pollin) {
    print join ' next : ', $receiver->recv();
    print "\n";

}