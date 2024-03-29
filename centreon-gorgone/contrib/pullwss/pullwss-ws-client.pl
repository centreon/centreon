#!/usr/bin/perl

# this is a work in progress to implement a minimal pullwss script.
# the objective is to connect to the distant websocket (seem to work) and to the zmq local socket (don't work for now)
# for zmq, both send() call don't output the same thing.
# the one in connect_com is silent, but nothing is retrieved from the other end, and the one in the main script make an error :
# Operation on closed socket at centreon-gorgone/pullwss-ws-client.pl line 40

use strict;
use warnings FATAL => 'all';
use Mojo::UserAgent;
use Data::Dumper;
use ZMQ::FFI qw(ZMQ_DEALER ZMQ_ROUTER ZMQ_ROUTER_HANDOVER ZMQ_IPV6 ZMQ_TCP_KEEPALIVE
    ZMQ_CONNECT_TIMEOUT ZMQ_DONTWAIT ZMQ_SNDMORE ZMQ_IDENTITY ZMQ_FD ZMQ_EVENTS
    ZMQ_LINGER ZMQ_SNDHWM ZMQ_RCVHWM ZMQ_RECONNECT_IVL);
our $socket;

sub connect_com {
    my (%options) = @_;

    my $context = ZMQ::FFI->new();
    # create a new socket in dealer mode.
    $socket = $context->socket(ZMQ_DEALER);
    $socket->set_identity('gorgone-voyou');
    $socket->die_on_error(1);



    my $err = $socket->connect('ipc:///tmp/gorgone/routing-171171119452399.ipc');
    print "err : $err and \@ is $@  and \! is $! \n\n";
    print "zmq connection seem established\n";
    print Dumper($socket);
    $socket->send('[ACK] [f735a55161a0090a6b9f7db9872df7ae3ed5264ae3bf20700f4b61bb9e474079a71267e98107433f30a496bc6d26503bf8a2407c1036575aeb3470ac8a55446e] {"data":null,"code":0}', ZMQ_DONTWAIT);

}


connect_com(type => 'ipc', path => '/tmp/gorgone/routing-1711561.ipc');
print Dumper($socket);
$socket->send('[ACK] [f735a55161a0090a6b9f7db9872df7ae3ed5264ae3bf20700f4b61bb9e474079a71267e98107433f30a496bc6d26503bf8a2407c1036575aeb3470ac8a55446e] {"data":null,"code":0}', ZMQ_DONTWAIT);

print "msg sent\n";
exit 0;


sub ping {
    my ($self) = shift;

    $self->{ping_timer} = time();

    my $message = '[REGISTERNODES] [] [] {"nodes":[{"id":"10","type":"wss","identity":"10"}]}';

    $self->{tx}->send({ text => $message });
}

sub wss_connect {
    my $proto  = 'wss';
    my ($self) = @_;
    if (!$self->{ua}) {
        $self->{ua} = Mojo::UserAgent->new();
        $self->{ua}->transactor->name('gorgone mojo');

        $self->{ua}->insecure(1);
    }
    $self->{ua}->websocket(
        $proto . '://192.168.56.105:8086/' =>
        { Authorization => 'Bearer 1234' } =>
        sub {
            my ($ua, $tx) = @_;
            $self->{tx}   = $tx;
            print "ok what the  ?\n";

            $self->{tx}->on(
                finish => sub {
                    my ($tx, $code, $reason) = @_;

                    print("websocket closed with status $code for reason $reason\n");
                }
            );
            $self->{tx}->on(
                message => sub {
                    my ($tx, $msg) = @_;
                    print "got a Message !! : $msg\n";
                    # We skip. Dont need to send it in gorgone-core
                    return undef if ($msg =~ /^\[ACK\]/);

                    if ($msg =~ /^\[.*\]/) {
                        print('[pullwss] websocket message: ' . $msg);

                    } else {
                        print("[pullwss] websocket message: $msg \n");
                    }
                }
            );

            print("[pullwss] websocket connected\n");
            ping($self);

        }
    );

    $self->{ua}->inactivity_timeout(120);
}
my $self = {};
wss_connect($self);
Mojo::IOLoop->singleton->recurring(60 => sub {
    print(time() . "Inside singleton recurring\n");
    #wss_connect($self);
    $self->{tx}->send({ text => "ceci est un message" });
    print "self is : " . $self->{ua} . "\n";

});
print "avant ioloop start\n";
Mojo::IOLoop->start() unless (Mojo::IOLoop->is_running);
print "après ioloop start\n"