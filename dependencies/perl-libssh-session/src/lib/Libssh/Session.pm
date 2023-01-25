package Libssh::Session;

use strict;
use warnings;
use Exporter qw(import);
use XSLoader;
use Time::HiRes;

our $VERSION = '0.9';

XSLoader::load('Libssh::Session', $VERSION);

use constant SSH_OK => 0;
use constant SSH_ERROR => -1;
use constant SSH_AGAIN => -2;
use constant SSH_EOF => -127;

use constant SSH_KNOWN_HOSTS_ERROR => -2;
use constant SSH_KNOWN_HOSTS_NOT_FOUND => -1;
use constant SSH_KNOWN_HOSTS_UNKNOWN => 0;
use constant SSH_KNOWN_HOSTS_OK => 1;
use constant SSH_KNOWN_HOSTS_CHANGED => 2;
use constant SSH_KNOWN_HOSTS_OTHER => 3;

use constant SSH_AUTH_METHOD_UNKNOWN => 0;
use constant SSH_AUTH_METHOD_NONE => 1;
use constant SSH_AUTH_METHOD_PASSWORD => 2;
use constant SSH_AUTH_METHOD_PUBLICKEY => 4;
use constant SSH_AUTH_METHOD_HOSTBASED => 8;
use constant SSH_AUTH_METHOD_INTERACTIVE => 16;
use constant SSH_AUTH_METHOD_GSSAPI_MIC => 32;

# Deprecated
use constant SSH_SERVER_ERROR => -1;
use constant SSH_SERVER_NOT_KNOWN => 0;
use constant SSH_SERVER_KNOWN_OK => 1;
use constant SSH_SERVER_KNOWN_CHANGED => 2;
use constant SSH_SERVER_FOUND_OTHER => 3;
use constant SSH_SERVER_FILE_NOT_FOUND => 4;

use constant SSH_PUBLICKEY_HASH_SHA1 => 0;
use constant SSH_PUBLICKEY_HASH_MD5 => 1;

use constant SSH_LOG_NOLOG => 0;
use constant SSH_LOG_WARNING => 1;
use constant SSH_LOG_PROTOCOL => 2;
use constant SSH_LOG_PACKET => 3;
use constant SSH_LOG_FUNCTIONS => 4;
use constant SSH_LOG_RARE => 1; # like WARNING

use constant SSH_AUTH_ERROR => -1;
use constant SSH_AUTH_SUCCESS => 0;
use constant SSH_AUTH_DENIED => 1;
use constant SSH_AUTH_PARTIAL => 2;
use constant SSH_AUTH_INFO => 3;
use constant SSH_AUTH_AGAIN => 4;

use constant SSH_NO_ERROR => 0;
use constant SSH_REQUEST_DENIED => 1;
use constant SSH_FATAL => 2;
use constant SSH_EINTR => 3;

our @EXPORT_OK = qw(
SSH_OK SSH_ERROR SSH_AGAIN SSH_EOF
SSH_LOG_NOLOG SSH_LOG_WARNING SSH_LOG_PROTOCOL SSH_LOG_PACKET SSH_LOG_FUNCTIONS
SSH_AUTH_ERROR SSH_AUTH_SUCCESS SSH_AUTH_DENIED SSH_AUTH_PARTIAL SSH_AUTH_INFO SSH_AUTH_AGAIN
SSH_NO_ERROR SSH_REQUEST_DENIED SSH_FATAL SSH_EINTR
SSH_AUTH_METHOD_UNKNOWN SSH_AUTH_METHOD_NONE SSH_AUTH_METHOD_PASSWORD SSH_AUTH_METHOD_PUBLICKEY
SSH_AUTH_METHOD_HOSTBASED SSH_AUTH_METHOD_INTERACTIVE SSH_AUTH_METHOD_GSSAPI_MIC
);
our @EXPORT = qw();
our %EXPORT_TAGS = ( 'all' => [ @EXPORT, @EXPORT_OK ] );

my $err;

sub set_err {
    my ($self, %options) = @_;

    $err = $options{msg};
    if ($self->{raise_error}) {
        die $err;
    }
    if ($self->{print_error}) {
        warn $err;
    }
}

sub set_blocking {
    my ($self, %options) = @_;

    ssh_set_blocking($self->{ssh_session}, $options{blocking});
}

sub is_blocking {
    my ($self, %options) = @_;

    ssh_is_blocking($self->{ssh_session});
}

sub error {
    my ($self, %options) = @_;

    if (defined($options{GetErrorSession}) && $options{GetErrorSession}) {
        $err = ssh_get_error_from_session($self->{ssh_session}) if (defined($self->{ssh_session}));
    }
    return $err;
}

sub new {
    my ($class, %options) = @_;
    my $self  = {};
    bless $self, $class;

    $self->{raise_error} = 0;
    $self->{print_error} = 0;
    $self->{ssh_session} = ssh_new();
    if (!defined($self->{ssh_session})) {
        $self->set_err(msg => 'ssh_new failed: cannot init session');
        return undef;
    }

    $self->{commands} = [];
    $self->{authenticated} = 0;
    $self->{channels} = {};
    return $self;
}

sub get_session {
    my ($self, %options) = @_;

    return $self->{ssh_session};
}

sub get_error {
    my ($self, %options) = @_;

    return ssh_get_error_from_session($self->{ssh_session});
}

sub check_uint {
    my ($self, %options) = @_;

    if (!defined($options{value}) || $options{value} eq '') {
        $self->set_err(msg => sprintf("option '%s' failed: please set a value", $options{type}));
        return 1;
    }
    if ($options{value} !~ /^\d+$/) {
        $self->set_err(msg => sprintf("option '%s' failed: please set a positive number", $options{type}));
        return 1;
    }

    return 0;
}

sub option_host {
    my ($self, %options) = @_;

    return ssh_options_set_host($self->{ssh_session}, $options{value});
}

sub option_port {
    my ($self, %options) = @_;

    return 1 if ($self->check_uint(value => $options{value}, type => 'port'));
    return ssh_options_set_port($self->{ssh_session}, $options{value});
}

sub option_user {
    my ($self, %options) = @_;

    return ssh_options_set_user($self->{ssh_session}, $options{value});
}

sub option_timeout {
    my ($self, %options) = @_;

    return 1 if ($self->check_uint(value => $options{value}, type => 'timeout'));
    return ssh_options_set_timeout($self->{ssh_session}, $options{value});
}

sub option_stricthostkeycheck {
    my ($self, %options) = @_;

    return 1 if ($self->check_uint(value => $options{value}, type => 'StrictHostKeyCheck'));
    return ssh_options_set_stricthostkeycheck($self->{ssh_session}, $options{value});
}

sub option_sshdir {
    my ($self, %options) = @_;

    return ssh_options_set_ssh_dir($self->{ssh_session}, $options{value});
}

sub option_knownhosts {
    my ($self, %options) = @_;

    return ssh_options_set_knownhosts($self->{ssh_session}, $options{value});
}

sub option_identity {
    my ($self, %options) = @_;

    return ssh_options_set_identity($self->{ssh_session}, $options{value});
}

sub option_logverbosity {
    my ($self, %options) = @_;

    return 1 if ($self->check_uint(value => $options{value}, type => 'LogVerbosity'));
    return ssh_options_set_log_verbosity($self->{ssh_session}, $options{value});
}

sub option_raiseerror {
    my ($self, %options) = @_;

    $self->{raise_error} = $options{value};
    return 0;
}

sub option_printerror {
    my ($self, %options) = @_;

    $self->{print_error} = $options{value};
    return 0;
}

sub options {
    my ($self, %options) = @_;

    foreach my $key (keys %options) {
        next if (!defined($options{$key}));

        my $ret;
        my $func = $self->can("option_" . lc($key));
        if (defined($func)) {
            $ret = $func->($self, value => $options{$key});
        } else {
            $self->set_err(msg => sprintf("option '%s' is not supported", $key));
            return SSH_ERROR;
        }
        if ($ret != SSH_OK) {
            # error from libssh (< 0)
            $self->set_err(msg => sprintf("option '%s' failed: %s", $key, ssh_get_error_from_session($self->{ssh_session}))) if ($ret < 0);
            return $ret;
        }
    }

    return SSH_OK;
}

# Deprecated
sub get_publickey {
    my ($self, %options) = @_;

    $self->{pubkey} = undef;
    return ssh_get_server_publickey($self->{ssh_session});
}

sub get_server_publickey {
    my ($self, %options) = @_;

    $self->{pubkey} = undef;
    return ssh_get_server_publickey($self->{ssh_session});
}

sub get_publickey_hash {
    my ($self, %options) = @_;

    my $hash_type = SSH_PUBLICKEY_HASH_SHA1;
    if (defined($options{Type}) &&
        ($options{Type} == SSH_PUBLICKEY_HASH_SHA1 || $options{Type} == SSH_PUBLICKEY_HASH_MD5)) {
        $hash_type = $options{Type};
    }

    return ssh_get_publickey_hash($self->{pubkey}, $hash_type);
}

sub get_hexa {
    my ($self, %options) = @_;

    return ssh_get_hexa($options{value});
}

# Deprecated. Use is_known_server
sub is_server_known {
    my ($self, %options) = @_;

    return ssh_session_is_known_server($self->{ssh_session});
}

sub is_known_server {
    my ($self, %options) = @_;

    return ssh_session_is_known_server($self->{ssh_session});
}

# Deprecated. Please use update_known_hosts
sub write_knownhost {
    my ($self, %options) = @_;

    return ssh_session_update_known_hosts($self->{ssh_session});
}

sub update_known_hosts {
    my ($self, %options) = @_;

    return ssh_session_update_known_hosts($self->{ssh_session});
}

sub verify_knownhost {
    my ($self, %options) = @_;

    my $ret = $self->is_known_server();

    $self->{pubkey} = $self->get_server_publickey();
    if (!defined($self->{pubkey})) {
        $self->set_err(msg => sprintf("get server pubkey failed: %s", ssh_get_error_from_session($self->{ssh_session})));
        return SSH_ERROR;
    }

    my $pubkey_hash = $self->get_publickey_hash();
    ssh_key_free($self->{pubkey});
    if (!defined($pubkey_hash)) {
        $self->set_err(msg => sprintf("get server pubkey hash failed: %s", ssh_get_error_from_session($self->{ssh_session})));
        return SSH_ERROR;
    }

    if ($ret == SSH_KNOWN_HOSTS_OK) {
        return SSH_OK;
    } elsif ($ret == SSH_KNOWN_HOSTS_ERROR) {
        $self->set_err(msg => sprintf("knownhost failed: %s", ssh_get_error_from_session($self->{ssh_session})));
    } elsif ($ret == SSH_KNOWN_HOSTS_NOT_FOUND || $ret == SSH_KNOWN_HOSTS_UNKNOWN) {
        if ($self->update_known_hosts() == SSH_OK) {
            return SSH_OK;
        }
        $self->set_err(msg => sprintf("knownhost write failed: %s", get_strerror()));
    } elsif ($ret == SSH_KNOWN_HOSTS_CHANGED) {
        return SSH_OK if (defined($options{SkipKeyProblem}) && $options{SkipKeyProblem});
        $self->set_err(msg => sprintf("knownhost failed: Host key for server changed: it is now: %s", 
                                      $self->get_hexa(value => $pubkey_hash)));
    } elsif ($ret == SSH_KNOWN_HOSTS_OTHER) {
        return SSH_OK if (defined($options{SkipKeyProblem}) && $options{SkipKeyProblem});
        $self->set_err(msg => sprintf("knownhost failed: The host key for this server was not found but an other type of key exists."));
    }

    return SSH_ERROR;
}

sub connect {
    my ($self, %options) = @_;
    my $skip_key_problem = defined($options{SkipKeyProblem}) ? $options{SkipKeyProblem} : 1;

    my $ret = ssh_connect($self->{ssh_session});
    if ($ret != SSH_OK) {
        $self->set_err(msg => sprintf("connect failed: %s", ssh_get_error_from_session($self->{ssh_session})));
        return $ret;
    }
    if (!(defined($options{connect_only}) && $options{connect_only} == 1)) {
        if (($ret = $self->verify_knownhost(SkipKeyProblem => $skip_key_problem)) != SSH_OK) {
            return $ret;
        }
    }

    return SSH_OK;
}

sub disconnect {
    my ($self) = @_;

    foreach my $channel_id (keys %{$self->{channels}}) {
        $self->close_channel(channel_id => $channel_id);
    }
    if ($self->is_connected() == 1) {
        ssh_disconnect($self->{ssh_session});
    }
    $self->{authenticated} = 0;
}

sub is_authenticated {
    my ($self, %options) = @_;

    return $self->{authenticated};
}

sub auth_gssapi {
    my ($self, %options) = @_;

    my $ret = ssh_userauth_gssapi($self->{ssh_session});
    if ($ret == SSH_AUTH_ERROR) {
        $self->set_err(msg => sprintf("authentification failed: %s", ssh_get_error_from_session($self->{ssh_session})));
    }
    $self->{authenticated} = 1 if ($ret == SSH_OK);

    return $ret;
}

sub auth_list {
    my ($self, %options) = @_;

    return ssh_userauth_list($self->{ssh_session});
}

sub auth_password {
    my ($self, %options) = @_;

    my $ret = ssh_userauth_password($self->{ssh_session}, $options{password});
    if ($ret == SSH_AUTH_ERROR) {
        $self->set_err(msg => sprintf("authentification failed: %s", ssh_get_error_from_session($self->{ssh_session})));
    }
    $self->{authenticated} = 1 if ($ret == SSH_OK);

    return $ret;
}

sub auth_publickey_auto {
    my ($self, %options) = @_;
    my $pass_defined = 1;

    if (!defined($options{passphrase})) {
        $options{passphrase} = '';
        $pass_defined = 0;
    }
    my $ret = ssh_userauth_publickey_auto($self->{ssh_session}, $options{passphrase}, $pass_defined);
    if ($ret == SSH_AUTH_ERROR) {
        $self->set_err(msg => sprintf("authentification failed: %s", ssh_get_error_from_session($self->{ssh_session})));
    }
    $self->{authenticated} = 1 if ($ret == SSH_OK);

    return $ret;
}

sub auth_none {
    my ($self, %options) = @_;

    my $ret = ssh_userauth_none($self->{ssh_session});
    if ($ret == SSH_AUTH_ERROR) {
        $self->set_err(msg => sprintf("authentification failed: %s", ssh_get_error_from_session($self->{ssh_session})));
    }
    $self->{authenticated} = 1 if ($ret == SSH_OK);

    return $ret;
}

sub auth_kbdint {
    my ($self, %options) = @_;

    my $ret = ssh_userauth_kbdint($self->{ssh_session});
    if ($ret == SSH_AUTH_ERROR) {
        $self->set_err(msg => sprintf("authentification failed: %s", ssh_get_error_from_session($self->{ssh_session})));
    }
    $self->{authenticated} = 1 if ($ret == SSH_OK);

    return $ret;
}

sub auth_kbdint_getnprmopts {
    my ($self, %options) = @_;

    my $ret = ssh_userauth_kbdint_getnprompts($self->{ssh_session});
    if ($ret == SSH_ERROR) {
        $self->set_err(msg => sprintf("failed to get number of keyboard interactive prompts: %s", ssh_get_error_from_session($self->{ssh_session})));
    }

    return $ret;
}

sub auth_kbdint_getname {
    my ($self, %options) = @_;

    return ssh_userauth_kbdint_getname($self->{ssh_session});
}

sub auth_kbdint_getinstruction {
    my ($self, %options) = @_;

    return ssh_userauth_kbdint_getinstruction($self->{ssh_session});
}

sub auth_kbdint_getprompt {
    my ($self, %options) = @_;

    my $ret = ssh_userauth_kbdint_getprompt($self->{ssh_session}, $options{index});
    if (!defined $ret) {
        $self->set_err(msg => sprintf("failed to get a prompt from a keyboard interactive message block: %s", ssh_get_error_from_session($self->{ssh_session})));
    }

    return $ret;
}

sub auth_kbdint_setanswer {
    my ($self, %options) = @_;

    my $ret = ssh_userauth_kbdint_setanswer($self->{ssh_session}, $options{index}, $options{answer});
    if ($ret < 0) {
        $self->set_err(msg => sprintf("failed to set an answer for a question from a keyboard interactive message block: %s", ssh_get_error_from_session($self->{ssh_session})));
    }

    return $ret;
}

sub get_fd {
    my ($self, %options) = @_;

    return ssh_get_fd($self->{ssh_session});
}

sub get_issue_banner {
    my ($self, %options) = @_;

    return ssh_get_issue_banner($self->{ssh_session});
}

#
# Channel functions
#

sub add_command {
    my ($self, %options) = @_;

    push @{$self->{commands}}, $options{command};
}

sub add_command_internal {
    my ($self, %options) = @_;
    my $timeout = (defined($options{timeout}) && int($options{timeout}) > 0) ? 
        $options{timeout} : 300;
    my $timeout_nodata = (defined($options{timeout_nodata}) && int($options{timeout_nodata}) > 0) ? 
        $options{timeout_nodata} : 120;

    my $channel_id = $self->open_channel();
    if ($channel_id !~ /^\d+\:\d+$/) {
        if (defined($options{command}->{callback})) {
            $options{command}->{callback}->(exit => SSH_ERROR, error_msg => 'cannot init channel', session => $self, userdata => $options{command}->{userdata});
        } else {
            push @{$self->{store_no_callback}}, { exit => SSH_ERROR, error_msg => 'cannot init channel', session => $self, userdata => $options{command}->{userdata} };
        }
        return undef;
    }

    $self->{slots}->{$channel_id} = $options{command};
    $self->{slots}->{$channel_id}->{timeout_counter} = $timeout;
    $self->{slots}->{$channel_id}->{timeout_nodata_counter} = $timeout_nodata;
    $self->{slots}->{$channel_id}->{stdout} = '';
    $self->{slots}->{$channel_id}->{stderr} = '';
    $self->{slots}->{$channel_id}->{read} = 0;

    if (defined($options{command}->{cmd}) && $options{command}->{cmd} ne '') {
        $self->channel_request_exec(
            channel => ${$self->{channels}->{$channel_id}},
            cmd => $options{command}->{cmd}
        );
    } else {
        $self->channel_request_shell(
            channel => ${$self->{channels}->{$channel_id}}
        );
    }
    if (defined($options{command}->{input_data})) {
        if ($self->channel_write(channel => ${$self->{channels}->{$channel_id}}, data => $options{command}->{input_data}) == SSH_ERROR) {
            $self->close_channel(channel_id => $channel_id);
            if (defined($options{command}->{callback})) {
                $options{command}->{callback}->(exit => SSH_ERROR, error_msg => 'cannot write in channel', session => $self, userdata => $options{command}->{userdata});
            } else {
                push @{$self->{store_no_callback}}, { exit => SSH_ERROR, error_msg => 'cannot write in channel', session => $self, userdata => $options{command}->{userdata} };
            }
            return undef;
        }

        # Force to finish it
        $self->channel_send_eof(channel => ${$self->{channels}->{$channel_id}});
    }
    return $channel_id;
}

sub channel_get_exit_status {
    my ($self, %options) = @_;

    return ssh_channel_get_exit_status($options{channel});
}

sub execute_read_channel {
    my ($self, %options) = @_;

    my $channel = ${$self->{channels}->{$options{channel_id}}};
    my $channel_id = $options{channel_id};

    # read stdout
    while (1) {
        my $result = ssh_channel_read($channel, 4092, 0, 1);
        if (defined($result->{message})) {
            $self->{slots}->{$channel_id}->{stdout} .= $result->{message};
        }

        last if ($result->{code} != 4092);
    }

    # read stderr
    while (1) {
        my $result = ssh_channel_read($channel, 4092, 1, 1);
        if (defined($result->{message})) {
            $self->{slots}->{$channel_id}->{stderr} .= $result->{message};
        }

        last if ($result->{code} != 4092);
    }

    if (ssh_channel_is_eof($channel) != 0) {
        $self->{slots}->{$channel_id}->{exit_code} = $self->channel_get_exit_status(channel => $channel);
        $self->close_channel(channel_id => $channel_id);

        my %callback_options = (
            exit => SSH_OK,
            session => $self,
            exit_code => $self->{slots}->{$channel_id}->{exit_code},
            userdata => $self->{slots}->{$channel_id}->{userdata},
            stdout => $self->{slots}->{$channel_id}->{stdout},
            stderr => $self->{slots}->{$channel_id}->{stderr},
        );
        if (defined($self->{slots}->{$channel_id}->{callback})) {
            $self->{slots}->{$channel_id}->{callback}->(%callback_options);
        } else {
            push @{$self->{store_no_callback}}, \%callback_options;
        }
        delete $self->{slots}->{$channel_id};
    } else {
        $self->{slots}->{$channel_id}->{read} = 1;
    }
}

sub execute_internal {
    my ($self, %options) = @_;
    my $parallel = (defined($options{parallel}) && int($options{parallel}) > 0) ? 
        $options{parallel} : 4;

    $self->{slots} = {};
    while (1) {
        while (scalar(keys %{$self->{slots}}) < $parallel && scalar(@{$self->{commands}}) > 0) {
            $self->add_command_internal(command => shift(@{$self->{commands}}), %options);
        }

        last if (scalar(keys %{$self->{slots}}) == 0);

        my @channels_array = ();
        foreach (keys %{$self->{slots}}) {
            $self->{slots}->{$_}->{read} = 0;
            push @channels_array, ${$self->{channels}->{$_}};
        }

        my $now = Time::HiRes::time();
        my $ret = ssh_channel_select_read(\@channels_array, 5);
        if ($ret->{code} == SSH_OK) {
            foreach (@{$ret->{channel_ids}}) {
                my ($session_id, $channel_id) = split /\./;

                $self->execute_read_channel(channel_id => $channel_id);
            }
        }
        my $now2 = Time::HiRes::time();

        # check timeout
        my $seconds = ($now2 - $now);
        foreach (keys %{$self->{slots}}) {
            $self->{slots}->{$_}->{timeout_counter} -= $seconds;
            if ($self->{slots}->{$_}->{read} == 0) {
                $self->{slots}->{$_}->{timeout_nodata_counter} -= $seconds;
            }

            if ($self->{slots}->{$_}->{timeout_counter} <= 0 ||
                $self->{slots}->{$_}->{timeout_nodata_counter} <= 0) {
                $self->close_channel(channel_id => $_);

                my %callback_options = (
                    exit => SSH_AGAIN,
                    session => $self,
                    exit_code => undef,
                    userdata => $self->{slots}->{$_}->{userdata},
                    stdout => $self->{slots}->{$_}->{stdout},
                    stderr => $self->{slots}->{$_}->{stderr},
                );
                if (defined($self->{slots}->{$_}->{callback})) {
                    $self->{slots}->{$_}->{callback}->(%callback_options);
                } else {
                    push @{$self->{store_no_callback}}, \%callback_options;
                }
                delete $self->{slots}->{$_};
            }
        }
    }
}

sub execute {
    my ($self, %options) = @_;

    push @{$self->{commands}}, @{$options{commands}} if (defined($options{commands}));
    $self->{store_no_callback} = [];
    $self->execute_internal(%options);
    $self->{commands} = [];

    return $self->{store_no_callback};
}

sub execute_simple {
    my ($self, %options) = @_;

    $self->{commands} = [ { cmd => $options{cmd}, input_data => $options{input_data} } ];
    $self->{store_no_callback} = [];
    $self->execute_internal(%options);
    $self->{commands} = [];

    return pop(@{$self->{store_no_callback}});
}

sub open_channel {
    my ($self, %options) = @_;

    my $channel = $self->channel_new();
    if (!defined($channel)) {
        return SSH_ERROR;
    }
    if ($self->channel_open_session(channel => $channel) != SSH_OK) {
        $self->channel_free(channel => $channel);
        return SSH_ERROR;
    }

    my $channel_id = ssh_channel_get_id($channel);
    $self->{channels}->{$channel_id} = \$channel;

    return $channel_id;
}

sub get_channel {
    my ($self, %options) = @_;

    if (!defined($options{channel_id}) || !defined($self->{channels}->{$options{channel_id}})) {
        return undef;
    }

    return ${$self->{channels}->{$options{channel_id}}};
}

sub close_channel {
    my ($self, %options) = @_;

    if (!defined($options{channel_id}) || !defined($self->{channels}->{$options{channel_id}})) {
        return undef;
    }
    $self->channel_close(channel => ${$self->{channels}->{$options{channel_id}}});
    $self->channel_free(channel => ${$self->{channels}->{$options{channel_id}}});

    delete $self->{channels}->{$options{channel_id}};
}

sub is_closed_channel {
    my ($self, %options) = @_;

    if (!defined($options{channel_id}) || !defined($self->{channels}->{$options{channel_id}})) {
        return undef;
    }

    return $self->channel_close(channel => ${$self->{channels}->{$options{channel_id}}});;
}

sub channel_new {
    my ($self, %options) = @_;

    return ssh_channel_new($self->{ssh_session});
}

sub channel_open_session {
    my ($self, %options) = @_;

    return ssh_channel_open_session($options{channel});
}

sub channel_write {
    my ($self, %options) = @_;

    return ssh_channel_write($options{channel}, $options{data});
}

sub channel_request_exec {
    my ($self, %options) = @_;

    return ssh_channel_request_exec($options{channel}, $options{cmd});
}

sub channel_request_shell {
    my ($self, %options) = @_;

    return ssh_channel_request_shell($options{channel});
}

sub channel_close {
    my ($self, %options) = @_;

    return ssh_channel_close($options{channel});
}

sub channel_free {
    my ($self, %options) = @_;

    return ssh_channel_free($options{channel});
}

sub channel_send_eof {
    my ($self, %options) = @_;

    return ssh_channel_send_eof($options{channel});
}

sub channel_is_eof {
    my ($self, %options) = @_;

    return ssh_channel_is_eof($options{channel});
}

sub channel_is_closed {
    my ($self, %options) = @_;

    return ssh_channel_is_closed($options{channel});
}

sub is_connected {
    my ($self, %options) = @_;

    return ssh_is_connected($self->{ssh_session});
}

sub DESTROY {
    my ($self) = @_;

    if (defined($self->{ssh_session})) {
        $self->disconnect();
        ssh_free($self->{ssh_session});
    }
}

1;

__END__

=head1 NAME

Libssh::Session - Support for the SSH protocol via libssh.

=head1 SYNOPSIS

  !/usr/bin/perl

  use strict;
  use warnings;
  use Libssh::Session qw(:all);

  my $session = Libssh::Session->new();
  if (!$session->options(host => "127.0.0.1", port => 22)) {
    print $session->error() . "\n";
    exit(1);
  }

  if ($session->connect() != SSH_OK) {
    print $session->error() . "\n";
    exit(1);
  }

  if ($session->auth_password(password => "password") != SSH_AUTH_SUCCESS) {
    printf("auth issue: %s\n", $session->error(GetErrorSession => 1));
    exit(1);
  }

  print "== authentification succeeded\n";

  sub my_callback {
    my (%options) = @_;

    print "================================================\n";
    print "=== exit = " . $options{exit} . "\n";
    if ($options{exit} == SSH_OK || $options{exit} == SSH_AGAIN) { # AGAIN means timeout
        print "=== exit_code = " . $options{exit_code} . "\n";
        print "=== userdata = " . $options{userdata} . "\n";
        print "=== stdout = " . $options{stdout} . "\n";
        print "=== stderr = " . $options{stderr} . "\n";
    } else {
        printf("error: %s\n", $session->error(GetErrorSession => 1));
    }
    print "================================================\n";

    #$options{session}->add_command(command => { cmd => 'ls -l', callback => \&my_callback, userdata => 'cmd 3'});
  }

  $session->execute(
    commands => [
        { cmd => 'ls -l', callback => \&my_callback, userdata => 'cmd 1'},
        { cmd => 'ls wanterrormsg', callback => \&my_callback, userdata => 'cmd 2 error'}
    ],
    timeout => 60, timeout_nodata => 30, parallel => 4
  );
  exit(0);

=head1 DESCRIPTION

C<Libssh::Session> is a perl interface to the libssh (L<http://www.libssh.org>)
library. It doesn't support all the library. It's working in progress.

Right now, you can authenticate and execute commands on a SSH server.

=head1 METHODS

=over 4

=item new

Create new Session object:

    my $session = Libssh::Session->new();


=item auth_publickey_auto ([ OPTIONS ])

Tries to automatically authenticate with public key and "none". returns SSH_AUTH_SUCCESS if it succeeds.

C<OPTIONS> are passed in a hash like fashion, using key and value pairs. Possible options are:

B<passphrase> - passphrase for the private key (if it's needed. Otherwise don't set the option).

=item auth_list ([ OPTIONS ])

Tries to retrieve a list of accepted authentication methods. Returns a bitfield of the following values:
SSH_AUTH_METHOD_UNKNOWN
SSH_AUTH_METHOD_NONE
SSH_AUTH_METHOD_PASSWORD
SSH_AUTH_METHOD_PUBLICKEY
SSH_AUTH_METHOD_HOSTBASED
SSH_AUTH_METHOD_INTERACTIVE
SSH_AUTH_METHOD_GSSAPI_MIC

The function auth_none() must be called first before the methods are available.

=item auth_password ([ OPTIONS ])

Try to authenticate by password. returns SSH_AUTH_SUCCESS if it succeeds.

C<OPTIONS> are passed in a hash like fashion, using key and value pairs. Possible options are:

B<password> - passphrase for the private key (if it's needed. Otherwise don't set the option).


=item auth_kbdint ([ OPTIONS ])

Try to authenticate through the "keyboard-interactive" method. Returns one of the following:
SSH_AUTH_ERROR:   A serious error happened\n
SSH_AUTH_DENIED:  Authentication failed : use another method\n
SSH_AUTH_PARTIAL: You've been partially authenticated, you still
                  have to use another method\n
SSH_AUTH_SUCCESS: Authentication success\n
SSH_AUTH_INFO:    The server asked some questions. Use
                  auth_kbdint_getnprmopts() and such to retrieve
                  and answer them.\n
SSH_AUTH_AGAIN:   In nonblocking mode, you've got to call this again
                  later.

=item auth_kbdint_getname ([ OPTIONS ])

Get the "name" of the message block. Returns undef if there isn't one or it couldn't be retrieved.

=item auth_kbdint_getinstruction ([ OPTIONS ])

Get the "instruction" of the message block. Returns undef if there isn't one or it couldn't be retrieved.

=item auth_kbdint_getnprmopts ([ OPTIONS ])

Get the number of authentication questions given by the server. This function can be used once you've called auth_kbdint() and the server responded with SSH_AUTH_INFO.

=item auth_kbdint_getprompt ([ OPTIONS ])

Get a prompt from a message block. This function can be used once you've called auth_kbdint() and the server responded with SSH_AUTH_INFO to retrieve one of the authentication questions. The total number of quesitons can be retrieved with auth_kbdint_getnprmopts(). Returns a reference to a hash table.

C<OPTIONS> are passed in a hash like fashion, using key and value pairs. Possible options are:

B<index> - The number of the prompt you want to retrieve.

The hash table returned has the following attributes:

B<text> - The prompt text.

B<echo> - '0' or '1' bool value whether or not the user's input should be echoed back.

=item auth_kbdint_setanswer ([ OPTIONS ])

Set the answer to a prompt from a message block.

C<OPTIONS> are passed in a hash like fashion, using key and value pairs. Possible options are:

B<index> - The number of the prompt you want to give an answer to.

B<answer> - The answer to the question. If reading ipnut from <STDIN> make sure to chomp() and append a "\0" character, otherwise it doesn't seem to work.


=item auth_none ([ OPTIONS ])

Try to authenticate through the "none" method. returns SSH_AUTH_SUCCESS if it succeeds.


=item connect ([ OPTIONS ])

Connect to the ssh server. returns SSH_OK if no error.
By default, the connect does the server check verification.

C<OPTIONS> are passed in a hash like fashion, using key and value pairs. Possible options are:

B<connect_only> - Set the value to '1' if you want to do the server check verification yourself.

B<SkipKeyProblem> - Returns SSH_OK even if there is a problem (server known changed or server found other) with the ssh server (set by default. Set '0' to disable).


=item disconnect ()

Disconnect from a session. The session can then be reused to open a new session.

The method take care of the current open channels.

B<Warning>: in many case, you should let the destructor do it!


=item execute_simple ([ OPTIONS ])

Execute a single command. Returns a reference to a hash table.

C<OPTIONS> are passed in a hash like fashion, using key and value pairs. Possible options are:

B<cmd> - The command to execute.

B<timeout> - Set the timeout in seconds for the global command execution (By default: 300).

B<timeout_nodata> - Set the timeout in seconds for no data received (By default: 120).

The hash table returned has the following attributes:

B<exit> - SSH_ERROR in case of failure. SSH_AGAIN in case of timeout. SSH_OK otherwise.

B<exit_code> - The exit code of the command executed. undef when timeout.

B<stdout> - The stdout of the executed command.

B<stderr> - The stderr of the executed command.


=item execute ([ OPTIONS ])

Execute multiple commands. If an error occured, please look how to handle it with the callback functions.

C<OPTIONS> are passed in a hash like fashion, using key and value pairs. Possible options are:

B<commands> - Reference to an array of hashes.

B<timeout> - Set the timeout in seconds for the global command execution (By default: 300). Each command has its own timeout.

B<timeout_nodata> - Set the timeout in seconds for no data received (By default: 120). Each command has its own timeout.

B<parallel> - Set the number of parallel commands launched (By default: 4).

B<Warning>: Execution times of callbacks count in timeout! Maybe you should save the datas and manages after the execute function.

Look the example above to see how to set the array for B<commands>.


=item error ( )

Returns the last error message. returns undef if no error.


=item get_publickey_hash ([ OPTIONS ])

Get a hash of the public key. If an error occured, undef is returned.

C<OPTIONS> are passed in a hash like fashion, using key and value pairs. Possible options are:

B<Type> - Hash type to used. Default: SSH_PUBLICKEY_HASH_SHA1. Can be: SSH_PUBLICKEY_HASH_MD5.


=item get_server_publickey ( )

Returns the server public key. If an error occured, undef is returned.

B<Warning>: should be used if you know what are you doing!


=item options ([ OPTIONS ])

Set options for the ssh session. If an error occured, != SSH_OK is returned.

C<OPTIONS> are passed in a hash like fashion, using key and value pairs. Possible options are:

B<Host> - The hostname or ip address to connect to.

B<User> - The username for authentication.

B<Port> - The port to connect to.

B<Timeout> - Set a timeout for the connection in seconds.

B<LogVerbosity> - Set the session logging verbosity (can be: SSH_LOG_NOLOG, SSH_LOG_RARE,...)

B<SshDir> - Set the ssh directory. The ssh directory is used for files like known_hosts and identity (private and public key). It may include "%s" which will be replaced by the user home directory.

B<KnownHosts> - Set the known hosts file name.

B<Identity> - Set the identity file name (By default identity, id_dsa and id_rsa are checked).

B<RaiseError> - Die if there is an error (By default: 0).

B<PrintError> - print in stdout if there is an error (By default: 0).

=back

=head1 LICENSE

This library is licensed under the Apache License 2.0. Details of this license can be found within the 'LICENSE' text file

=head1 AUTHOR

Quentin Garnier <qgarnier@centreon.com>

=cut
