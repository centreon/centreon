#!/usr/bin/perl
use strict;
use warnings;
use v5.10;

$| = 1; # autoflush stdout after each print

use ZMQ::FFI qw(ZMQ_DEALER);
use ZMQ::FFI::Constants qw(ZMQ_PULL ZMQ_PUSH ZMQ_SUB ZMQ_DONTWAIT ZMQ_SNDMORE ZMQ_ROUTER ZMQ_RCVHWM ZMQ_SNDHWM);

use EV;

# creating the context
my $context = ZMQ::FFI->new();
# create a new socket in router mode
my $router = $context->socket(ZMQ_ROUTER);

$router->set_identity("test-router");
$router->die_on_error(1);

my $ipcLocation = 'ipc:///tmp/dealer-test1';
my $err = $router->bind($ipcLocation);
print "binding to ipc $ipcLocation, err is : $err \n";

print "getting the first message from the dealer to know it's identity\n";
my ($identity, $msg) = $router->recv_multipart();
print "dealer sent : $msg\n";
sleep(5);

for (1..100){
    $router->send_multipart([$identity, 'looping message']);
}
print "all message were sent\n";
sleep(5);
$router->close();
print "script ended\n";