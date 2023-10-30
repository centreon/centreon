use strict;
use warnings;
use Mojo::UserAgent;

my $ua = Mojo::UserAgent->new();
# ws or wss
$ua->websocket(
    'ws://127.0.0.1:8086/' => sub {
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
            }
        );

        $tx->send({json => { username => 'admin', password => 'plop' } });
        $tx->send({json => { method => 'POST', uri => '/core/action/command', userdata => 'command1', data => [ { command => 'ls' } ] } });
    }
);
$ua->inactivity_timeout(120);
Mojo::IOLoop->start() unless (Mojo::IOLoop->is_running);

exit(0);
