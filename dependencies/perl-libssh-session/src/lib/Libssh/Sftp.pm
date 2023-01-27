package Libssh::Sftp;

use strict;
use warnings;
use POSIX;
use Libssh::Session;
use Exporter qw(import);

our $VERSION = '0.9';

use constant SSH_OK => 0;
use constant SSH_ERROR => -1;
use constant SSH_AGAIN => -2;
use constant SSH_EOF => -127;

use constant SSH_FX_OK => 0;
use constant SSH_FX_EOF => 1;
use constant SSH_FX_NO_SUCH_FILE => 2;
use constant SSH_FX_PERMISSION_DENIED => 3;
use constant SSH_FX_FAILURE => 4;
use constant SSH_FX_BAD_MESSAGE => 5;
use constant SSH_FX_NO_CONNECTION => 6;
use constant SSH_FX_CONNECTION_LOST => 7;
use constant SSH_FX_OP_UNSUPPORTED => 8;
use constant SSH_FX_INVALID_HANDLE => 9;
use constant SSH_FX_NO_SUCH_PATH => 10;
use constant SSH_FX_FILE_ALREADY_EXISTS => 11;
use constant SSH_FX_WRITE_PROTECT => 12;
use constant SSH_FX_NO_MEDIA => 13;

use constant SSH_FILEXFER_TYPE_REGULAR => 1;
use constant SSH_FILEXFER_TYPE_DIRECTORY => 2;
use constant SSH_FILEXFER_TYPE_SYMLINK => 3;
use constant SSH_FILEXFER_TYPE_SPECIAL => 4;
use constant SSH_FILEXFER_TYPE_UNKNOWN => 5;

our @EXPORT_OK = qw(
SSH_FX_OK SSH_FX_EOF SSH_FX_NO_SUCH_FILE SSH_FX_PERMISSION_DENIED SSH_FX_FAILURE
SSH_FX_BAD_MESSAGE SSH_FX_NO_CONNECTION SSH_FX_CONNECTION_LOST SSH_FX_OP_UNSUPPORTED
SSH_FX_INVALID_HANDLE SSH_FX_NO_SUCH_PATH SSH_FX_FILE_ALREADY_EXISTS
SSH_FX_WRITE_PROTECT SSH_FX_NO_MEDIA
SSH_FILEXFER_TYPE_REGULAR SSH_FILEXFER_TYPE_DIRECTORY SSH_FILEXFER_TYPE_SYMLINK
SSH_FILEXFER_TYPE_SPECIAL SSH_FILEXFER_TYPE_UNKNOWN
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

sub error {
    my ($self, %options) = @_;

    return $err;
}

sub init {
    my ($self, %options) = @_;

    if (!defined($options{session}) ||
        !$options{session}->isa('Libssh::Session')) {
        $self->set_err(msg => 'error allocating SFTP session: need to set session option');
        return SSH_ERROR;
    }
    my $session = $options{session}->get_session();
    if (!defined($session) || ref($session) ne 'ssh_session') {
        $self->set_err(msg => 'error allocating SFTP session: need to have a session init');
        return SSH_ERROR;
    }
    if ($options{session}->is_authenticated() == 0) {
        $self->set_err(msg => 'error allocating SFTP session: need to have a session authenticated');
        return SSH_ERROR;
    }

    $self->{ssh_session} = $options{session};
    $self->{sftp_session} = sftp_new($session);
    if (!defined($self->{sftp_session})) {
        $self->set_err(msg => 'error allocating SFTP session: ' . $options{session}->get_error());
        return SSH_ERROR;
    }

    my $ret = sftp_init($self->{sftp_session});
    if ($ret != SSH_OK) {
        my $msg = 'error initializing SFTP session: ' . sftp_get_error($self->{sftp_session});
        sftp_free($self->{sftp_session});
        $self->{sftp_session} = undef;
        $self->set_err(msg => $msg);
        return $ret;
    }

    return SSH_OK;
}

sub new {
    my ($class, %options) = @_;
    my $self  = {};
    bless $self, $class;

    $self->{raise_error} = 0;
    $self->{print_error} = 0;
    $self->{stp_session} = undef;
    $self->{ssh_session} = undef;
    if (defined($options{session}) &&
        $self->init(session => $options{session}) != SSH_OK) {
        return undef;
    }

    return $self;
}

sub option_session {
    my ($self, %options) = @_;

    $self->{raise_error} = $options{value};
    return 0;
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
        my $ret;

        my $func = $self->can("option_" . lc($key));
        if (defined($func)) {
            $ret = $func->($self, value => $options{$key});
        } else {
            $self->set_err(msg => sprintf("option '%s' is not supported", $key));
            return SSH_ERROR;
        }
        if ($ret != 0) {
            $self->set_err(msg => sprintf("option '%s' failed: %s", $key)) if ($ret < 0);
            return $ret;
        }
    }

    return SSH_OK;
}

sub stat_file {
    my ($self, %options) = @_;

    if (!defined($self->{sftp_session})) {
        $self->set_err(msg => 'error: please attach the session');
        return SSH_ERROR;
    }

    return sftp_lstat($self->{sftp_session}, $options{file});
}

sub list_dir {
    my ($self, %options) = @_;

    my $result = { code => SSH_ERROR, files => [] };
    if (!defined($self->{sftp_session})) {
        $self->set_err(msg => 'error: please attach the session');
        return $result;
    }

    my $handle_dir = $self->opendir(dir => $options{dir});
    if (!defined($handle_dir)) {
        $self->set_err(msg => sprintf("Directory not opened: %s", $self->get_msg_error()));
        return $result;
    }

    while ((my $attribute = $self->readdir(handle_dir => $handle_dir))) {
        push @{$result->{files}}, $attribute;
    }

    if ($self->dir_eof(handle_dir => $handle_dir) == 0) {
        $self->set_err(msg => sprintf("Can't list directory: %s", $self->get_msg_error()));
        $self->closedir(handle_dir => $handle_dir);
        return $result;
    }

    if ($self->closedir(handle_dir => $handle_dir) != SSH_OK) {
        $self->set_err(msg => sprintf("Can't close directory: %s", $self->get_msg_error()));
        return $result;
    }

    $result->{code} = SSH_OK;
    return $result;
}

sub opendir {
    my ($self, %options) = @_;

    return sftp_opendir($self->{sftp_session}, $options{dir});
}

sub mkdir {
    my ($self, %options) = @_;

    if (!defined($self->{sftp_session})) {
        $self->set_err(msg => 'error allocating SFTP session: ' . $options{session}->get_error());
        return undef;
    }

    my $mode = defined($options{mode}) ? $options{mode} : 0;
    my $code = sftp_mkdir($self->{sftp_session}, $options{dir}, $mode);
    if ($code != SSH_OK) {
        $self->set_err(msg => sprintf("Can't create directory: %s", $self->get_msg_error()));
    }

    return $code;
}

sub rmdir {
    my ($self, %options) = @_;

    if (!defined($self->{sftp_session})) {
        $self->set_err(msg => 'error allocating SFTP session: ' . $options{session}->get_error());
        return undef;
    }

    my $code = sftp_rmdir($self->{sftp_session}, $options{dir});
    if ($code != SSH_OK) {
        $self->set_err(msg => sprintf("Can't remove directory: %s", $self->get_msg_error()));
    }

    return $code;
}

sub rename {
    my ($self, %options) = @_;

    if (!defined($self->{sftp_session})) {
        $self->set_err(msg => 'error allocating SFTP session: ' . $options{session}->get_error());
        return undef;
    }

    my $code = sftp_rename($self->{sftp_session}, $options{original}, $options{newname});
    if ($code != SSH_OK) {
        $self->set_err(msg => sprintf("Can't rename '%s' -> '%s': %s", $options{original}, $options{newname}, $self->get_msg_error()));
    }

    return $code;
}

sub readdir {
    my ($self, %options) = @_;

    return sftp_readdir($self->{sftp_session}, $options{handle_dir});
}

sub dir_eof {
    my ($self, %options) = @_;

    return sftp_dir_eof($options{handle_dir});
}

sub closedir {
    my ($self, %options) = @_;

    return sftp_closedir($options{handle_dir});
}

sub server_version {
    my ($self, %options) = @_;

    return sftp_server_version($self->{sftp_session});
}

sub canonicalize_path {
    my ($self, %options) = @_;

    return sftp_canonicalize_path($self->{sftp_session}, $options{path});
}

sub open {
    my ($self, %options) = @_;

    if (!defined($self->{sftp_session})) {
        $self->set_err(msg => 'error allocating SFTP session: ' . $options{session}->get_error());
        return undef;
    }
    if (!defined($options{file}) || $options{file} eq '') {
        $self->set_err(msg => 'please specify file option');
        return undef;
    }

    my $accesstype = defined($options{accesstype}) ? $options{accesstype} : 0;
    my $mode = defined($options{mode}) ? $options{mode} : 0;
    my $file = sftp_open($self->{sftp_session}, $options{file}, $accesstype, $mode);
    if (!defined($file)) {
        $self->set_err(msg => sprintf("Can't open file: %s", $self->get_msg_error()));
        return undef;
    }

    return $file;
}

sub read {
    my ($self, %options) = @_;

    my $no_close = defined($options{no_close}) ? $options{no_close} : 0;

    if (!defined($options{handle_file})) {
        $self->set_err(msg => 'please specify handle file option');
        return undef;
    }

    my $content = '';
    while (1) {
        my $ret = sftp_read($options{handle_file}, 8092);
        if ($ret->{code} < 0) {
            $self->set_err(msg => sprintf("Can't read file: %s", $self->get_msg_error()));
            $self->close(handle_file => $options{handle_file});
            return SSH_ERROR;
        }
        last if ($ret->{code} == 0);
        $content .= $ret->{data};
    }

    if ($no_close == 0 && $self->close(handle_file => $options{handle_file}) != SSH_OK) {
        return SSH_ERROR;
    }

    return (SSH_OK, $content);
}

sub write {
    my ($self, %options) = @_;

    my $data = defined($options{data}) ? $options{data} : '';
    my $no_close = defined($options{no_close}) ? $options{no_close} : 0;
    if (!defined($options{handle_file})) {
        $self->set_err(msg => 'please specify handle file option');
        return undef;
    }

    my $nwritten = sftp_write($options{handle_file}, $data);
    if ($nwritten < 0) {
        $self->set_err(msg => sprintf("Can't write data to file: %s", $self->get_msg_error()));
        $self->close(handle_file => $options{handle_file});
        return SSH_ERROR;
    }

    if ($no_close == 0 && $self->close(handle_file => $options{handle_file}) != SSH_OK) {
        return SSH_ERROR;
    }

    return SSH_OK;
}

sub close {
    my ($self, %options) = @_;

    my $code = sftp_close($options{handle_file});
    if ($code != SSH_OK) {
         $self->set_err(msg => sprintf("Can't close file: %s", $self->get_msg_error()));
    }

    return $code;
}

sub unlink {
    my ($self, %options) = @_;

    if (!defined($self->{sftp_session})) {
        $self->set_err(msg => 'error allocating SFTP session: ' . $options{session}->get_error());
        return undef;
    }
    if (!defined($options{file}) || $options{file} eq '') {
        $self->set_err(msg => 'please specify file option');
        return undef;
    }

    my $code = sftp_unlink($self->{sftp_session}, $options{file});
    if ($code != SSH_OK) {
        $self->set_err(msg => sprintf("Can't unlink file: %s", $self->get_msg_error()));
    }

    return $code;
}

sub copy_file {
    my ($self, %options) = @_;
    my ($fh, $dst, $buffer);
    require bytes;

    if (!CORE::open($fh, '<', $options{src})) {
        $self->set_err(msg => sprintf("Can't open file '%s': %s", $options{src}, $!));
        return -1;
    }
    $dst = $self->open(file => $options{dst}, accesstype => O_WRONLY|O_CREAT|O_TRUNC, mode => defined($options{mode}) ? $options{mode} : 0644);
    if (!defined($dst)) {
        CORE::close($fh);
        return -1;
    }

    my $timeout = defined($options{timeout}) ? $options{timeout} : 60;
    my $chunk = defined($options{chunk}) ? $options{chunk} : 50000;
    while (my $nread = sysread($fh, $buffer, $chunk)) {
        my $nwritten = 0;
        while ($nwritten < $nread) {
            my $written;

            eval {
                local $SIG{ALRM} = sub { die 'Timeout by signal ALARM'; };
                alarm($timeout);
                $written = sftp_write_len($dst, $buffer, $nread);
                alarm(0);
            };
            if ($@ || $written < 0) {
                $self->set_err(
                    msg => sprintf("Can't write data to file: %s", defined($written) && $written < 0 ? $self->get_msg_error() : $@)
                );
                $self->close(handle_file => $dst);
                CORE::close($fh);
                return -1;
            }

            $buffer = bytes::substr($buffer, $nwritten);
            $nwritten += $written;
        }
    }

    $self->close(handle_file => $dst);
    CORE::close($fh);
    return 0;
}

sub get_msg_error {
    my ($self, %options) = @_;

    my $error_code = sftp_get_error($self->{sftp_session});
    my $mapping = {
        SSH_FX_OK                   , 'no error',
        SSH_FX_EOF                  , 'end-of-file encountered',
        SSH_FX_NO_SUCH_FILE         , 'file does not exist',
        SSH_FX_PERMISSION_DENIED    , 'permission denied',
        SSH_FX_FAILURE              , 'generic failure',
        SSH_FX_BAD_MESSAGE          , 'garbage received from server',
        SSH_FX_NO_CONNECTION        , 'no connection has been set up',
        SSH_FX_CONNECTION_LOST      , 'there was a connection, but we lost it',
        SSH_FX_OP_UNSUPPORTED       , 'operation not supported by libssh yet',
        SSH_FX_INVALID_HANDLE       , 'invalid file handle',
        SSH_FX_NO_SUCH_PATH         , 'no such file or directory path exists',
        SSH_FX_FILE_ALREADY_EXISTS  , 'an attempt to create an already existing file or directory has been made',
        SSH_FX_WRITE_PROTECT        , 'write-protected filesystem',
        SSH_FX_NO_MEDIA             , 'no media was in remote drive',
    };
    if (defined($mapping->{$error_code})) {
        return $mapping->{$error_code};
    }
    return 'unknown';
}

sub DESTROY {
    my ($self) = @_;

    if (defined($self->{sftp_session})) {
        sftp_free($self->{sftp_session});
    }
}

1;

__END__

=head1 NAME

Libssh::Sftp - Support for sftp via libssh.

=head1 SYNOPSIS

  !/usr/bin/perl

  use strict;
  use warnings;
  
  
  

=head1 DESCRIPTION

C<Libssh::Sftp> is a perl interface to the libssh (L<http://www.libssh.org>)
library. It doesn't support all the library. It's working in progress.

=head1 METHODS

=over 4

=item new

Create new Sftp object:

    my $sftp = Libssh::Sftp->new();

=item error ( )

Returns the last error message; returns undef if no error.

=back

=cut
