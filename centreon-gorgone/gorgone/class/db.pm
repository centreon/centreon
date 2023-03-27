# 
# Copyright 2019 Centreon (http://www.centreon.com/)
#
# Centreon is a full-fledged industry-strength solution that meets
# the needs in IT infrastructure and application monitoring for
# service performance.
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
#     http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.
#

package gorgone::class::db;

use strict;
use warnings;
use DBI;

sub new {
    my ($class, %options) = @_;
    my %defaults = (
       logger => undef,
       db => undef,
       dsn => undef,
       host => "localhost",
       user => undef,
       password => undef,
       port => 3306,
       force => 0,
       type => "mysql"
    );
    my $self = {%defaults, %options};
    $self->{type} = 'mysql' if (!defined($self->{type}));
    
    # strip double-quotes
    if (defined($self->{dsn})) {
        $self->{dsn} =~ s/^\s*"//;
        $self->{dsn} =~ s/"\s*$//;
    }

    $self->{die} = defined($options{die}) ? 1 : 0;
    $self->{instance} = undef;
    $self->{transaction_begin} = 0;
    bless $self, $class;
    return $self;
}

# Getter/Setter DB name
sub type {
    my $self = shift;
    if (@_) {
        $self->{type} = shift;
    }
    return $self->{type};
}

sub getInstance {
	my ($self) = @_;

	return $self->{instance};
}

# Getter/Setter DB name
sub db {
    my $self = shift;
    if (@_) {
        $self->{db} = shift;
    }
    return $self->{db};
}

sub sameParams {
    my ($self, %options) = @_;

    my $params = '';
    if (defined($self->{dsn})) {
        $params = $self->{dsn};
    } else {
       $params = $self->{host} . ':' . $self->{port} . ':' . $self->{db};
    }
    $params .= ':' . $self->{user} . ':' . $self->{password};

    my $paramsNew = '';
    if (defined($options{dsn})) {
        $paramsNew = $options{dsn};
    } else {
       $paramsNew = $options{host} . ':' . $options{port} . ':' . $options{db};
    }
    $params .= ':' . $options{user} . ':' . $options{password};

    return ($paramsNew eq $params) ? 1 : 0;
}

# Getter/Setter DB host
sub host {
    my $self = shift;
    if (@_) {
        $self->{host} = shift;
    }
    return $self->{host};
}

# Getter/Setter DB port
sub port {
    my $self = shift;
    if (@_) {
        $self->{port} = shift;
    }
    return $self->{port};
}

# Getter/Setter DB user
sub user {
    my $self = shift;
    if (@_) {
        $self->{user} = shift;
    }
    return $self->{user};
}

# Getter/Setter DB force
#     force 2 should'nt be used with transaction
sub force {
    my $self = shift;
    if (@_) {
        $self->{force} = shift;
    }
    return $self->{force};
}

# Getter/Setter DB password
sub password {
    my $self = shift;
    if (@_) {
        $self->{password} = shift;
    }
    return $self->{password};
}

sub last_insert_id {
    my $self = shift;
    return $self->{instance}->last_insert_id(undef, undef, undef, undef);
}

sub set_inactive_destroy {
    my $self = shift;

    if (defined($self->{instance})) {
        $self->{instance}->{InactiveDestroy} = 1;
    }
}

sub transaction_mode {
    my ($self, $mode) = @_;

    my $status;
    if (!defined($self->{instance})) {
        $status = $self->connect();
        return -1 if ($status == -1);
    }

    if ($mode) {
        $status = $self->{instance}->begin_work();
        if (!$status) {
            $self->error($self->{instance}->errstr, 'begin work');
            return -1;
        }
        $self->{transaction_begin} = 1;
    } else {
        $self->{transaction_begin} = 0;
        $self->{instance}->{AutoCommit} = 1;
    }

    return 0;
}

sub commit {
    my ($self) = @_;

    if (!defined($self->{instance})) {
        $self->{transaction_begin} = 0;
        return -1;
    }

    my $status = $self->{instance}->commit();
    $self->{transaction_begin} = 0;

    if (!$status) {
        $self->error($self->{instance}->errstr, 'commit');
        return -1;
    }

    return 0;
}

sub rollback {
    my ($self) = @_;

    $self->{instance}->rollback() if (defined($self->{instance}));
    $self->{transaction_begin} = 0;
}

sub kill {
    my $self = shift;

    if (defined($self->{instance})) {
        $self->{logger}->writeLogInfo("KILL QUERY\n");
        my $rv = $self->{instance}->do("KILL QUERY " . $self->{instance}->{'mysql_thread_id'});
        if (!$rv) {
            my ($package, $filename, $line) = caller;
            $self->{logger}->writeLogError("MySQL error : " . $self->{instance}->errstr . " (caller: $package:$filename:$line)");
        }
    }
}

# Connection initializer
sub connect() {
    my $self = shift;
    my ($status, $count) = (0, 0);

    while (1) {
        $self->{port} = 3306 if (!defined($self->{port}) && $self->{type} eq 'mysql');
        if (defined($self->{dsn})) {
            $self->{instance} = DBI->connect(
                "DBI:".$self->{dsn}, $self->{user}, $self->{password},
                {
                    RaiseError => 0,
                    PrintError => 0,
                    AutoCommit => 1,
                    mysql_enable_utf8 => 1
                }
            );
        } elsif ($self->{type} =~ /SQLite/i) {
            $self->{instance} = DBI->connect(
                "DBI:".$self->{type} 
                    .":".$self->{db},
                $self->{user},
                $self->{password},
                { RaiseError => 0, PrintError => 0, AutoCommit => 1, sqlite_unicode => 1 }
            );
        } else {
            $self->{instance} = DBI->connect(
                "DBI:".$self->{type} 
                    .":".$self->{db}
                    .":".$self->{host}
                    .":".$self->{port},
                $self->{user},
                $self->{password},
                {
                    RaiseError => 0,
                    PrintError => 0,
                    AutoCommit => 1,
                    mysql_enable_utf8 => 1
                }
            );
        }
        if (defined($self->{instance})) {
            last;
        }

        my ($package, $filename, $line) = caller;
        $self->{logger}->writeLogError("MySQL error : cannot connect to database '" . 
            (defined($self->{db}) ? $self->{db} : $self->{dsn}) . "': " . $DBI::errstr . " (caller: $package:$filename:$line) (try: $count)"
        );
        if ($self->{force} == 0 || ($self->{force} == 2 && $count == 1)) {
            $self->{lastError} = "MySQL error : cannot connect to database '" . 
                (defined($self->{db}) ? $self->{db} : $self->{dsn}) . "': " . $DBI::errstr;
            $status = -1;
            last;
        }
        sleep(1);
        $count++;
    }

    return $status;
}

# Destroy connection
sub disconnect {
    my $self = shift;
    my $instance = $self->{instance};
    if (defined($instance)) {
        $instance->disconnect;
        $self->{instance} = undef;
    }
}

sub do {
    my ($self, $query) = @_;

    if (!defined($self->{instance})) {
        if ($self->connect() == -1) {
            $self->{logger}->writeLogError("Cannot connect to database");
            return -1;
        }
    }
    my $numrows = $self->{instance}->do($query);
    die $self->{instance}->errstr if !defined $numrows;
    return $numrows;
}

sub error {
    my ($self, $error, $query) = @_;
    my ($package, $filename, $line) = caller 1;

    chomp($query);
    $self->{lastError} = "SQL error: $error (caller: $package:$filename:$line)
Query: $query
";
    $self->{logger}->writeLogError($error);
    if ($self->{transaction_begin} == 1) {
        $self->rollback();
    }
    $self->disconnect();
    $self->{instance} = undef;
}

sub prepare {
    my ($self, $query) = @_;

    return $self->query({ query => $query, prepare_only => 1 });
}

sub query {
    my ($self) = shift;
    my ($status, $count) = (0, -1);
    my $statement_handle;

    while (1) {
        if (!defined($self->{instance})) {
            $status = $self->connect();
            if ($status == -1) {
                last;
            }
        }

        $count++;
        $statement_handle = $self->{instance}->prepare($_[0]->{query});
        if (!defined($statement_handle)) {
            $self->error($self->{instance}->errstr, $_[0]->{query});
            $status = -1;
            last if ($self->{force} == 0 || ($self->{force} == 2 && $count == 1));
            sleep(1);
            next;
        }

        if (defined($_[0]->{prepare_only})) {
            return $statement_handle if ($self->{die} == 1);
            return ($status, $statement_handle);
        }

        my $rv;
        if (defined($_[0]->{bind_values}) && scalar(@{$_[0]->{bind_values}}) > 0) {
            $rv = $statement_handle->execute(@{$_[0]->{bind_values}});
        } else {
            $rv = $statement_handle->execute();
        }
        if (!$rv) {
            $self->error($statement_handle->errstr, $_[0]->{query});
            $status = -1;
            last if ($self->{force} == 0 || ($self->{force} == 2 && $count == 1));
            sleep(1);
            next;
        }

        last;
    }

    if ($self->{die} == 1) {
        die $self->{lastError} if ($status == -1);
        return $statement_handle;
    }

    return ($status, $statement_handle);
}

1;
