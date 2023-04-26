use strict;
use warnings;
use Mojolicious::Lite;
use Mojo::Server::Daemon;
use IO::Socket::SSL;
use DateTime;

sub sigalrm_handler
{
  printf (STDOUT "Timeout: Timeout Error Occured.\n");
  alarm(10);
}
$SIG{ALRM} = \&sigalrm_handler;


plugin 'basic_auth_plus';

my $clients = {};

IO::Socket::SSL::set_defaults(SSL_passwd_cb => sub { return 'secret' } );

websocket '/echo' => sub {
    my $self = shift;

    print sprintf("Client connected: %s\n", $self->tx->connection);
    my $ws_id = sprintf "%s", $self->tx->connection;
    $clients->{$ws_id} = $self->tx;

    $self->on(message => sub {
        my ($self, $msg) = @_;

        my $dt   = DateTime->now( time_zone => 'Asia/Tokyo');

        for (keys %$clients) {
            $clients->{$_}->send({json => {
                hms  => $dt->hms,
                text => $msg,
            }});
        }
    });

    $self->on(finish => sub {
        my ($self, $code, $reason) = @_;

        print "Client disconnected: $code\n";
        delete $clients->{ $self->tx->connection };
    });
};

get '/' => sub { 
    my $self = shift;

    $self->render(json => { message => 'ok' })
      if $self->basic_auth(
        "Realm Name" => {
            username => 'username',
            password => 'password'
        }
    );
};

my $daemon = Mojo::Server::Daemon->new(
  app    => app,
  listen => ["https://*:3000?reuse=1&cert=/etc/pki/tls/certs/localhost.crt&key=/etc/pki/tls/private/localhost.key"]
);
alarm(10);
$daemon->run();
