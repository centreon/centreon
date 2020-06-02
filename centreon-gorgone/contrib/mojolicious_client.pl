use strict;
use warnings;
use Mojo::UserAgent;

my $ua = Mojo::UserAgent->new();
$ua->websocket(
    'wss://127.0.0.1:3000/echo' => sub {
        my ($ua, $tx) = @_;

        print "error: ", $tx->res->error->{message}, "\n" if $tx->res->error;
        print 'WebSocket handshake failed!\n' and return unless $tx->is_websocket;

        $tx->on(
            finish => sub {
                my ($tx, $code, $reason) = @_;
                print "WebSocket closed with status $code.\n";
            }
        );
        $tx->on(
            message => sub {
                my ($tx, $msg) = @_;
                print "WebSocket message: $msg\n";
                #$tx->finish;
            }
        );
        $tx->send('Hi!');
    }
);
Mojo::IOLoop->start() unless (Mojo::IOLoop->is_running);

exit(0);
