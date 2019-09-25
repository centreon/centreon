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
    my %defaults =
      (
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

    $self->{instance} = undef;
    $self->{args} = [];
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

# Getter/Setter DB name
sub db {
    my $self = shift;
    if (@_) {
        $self->{db} = shift;
    }
    return $self->{db};
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

sub quote {
    my $self = shift;

    if (defined($self->{instance})) {
        return $self->{instance}->quote($_[0]);
    }
    my $num = scalar(@{$self->{args}});
    push @{$self->{args}}, $_[0];
    return "##__ARG__$num##";
}

sub set_inactive_destroy {
    my $self = shift;

    if (defined($self->{instance})) {
        $self->{instance}->{InactiveDestroy} = 1;
    }
}

sub transaction_mode {
    my ($self, $status) = @_;

    if ($status) {
        $self->{instance}->begin_work;
    } else {
        $self->{instance}->{AutoCommit} = 1;
    }
}

sub commit { shift->{instance}->commit; }

sub rollback { shift->{instance}->rollback; }

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
                { RaiseError => 0, PrintError => 0, AutoCommit => 1 }
            );
        } elsif ($self->{type} =~ /SQLite/i) {
            $self->{instance} = DBI->connect(
                "DBI:".$self->{type} 
                    .":".$self->{db},
                $self->{user},
                $self->{password},
                { RaiseError => 0, PrintError => 0, AutoCommit => 1 }
            );
        } else {
            $self->{instance} = DBI->connect(
                "DBI:".$self->{type} 
                    .":".$self->{db}
                    .":".$self->{host}
                    .":".$self->{port},
                $self->{user},
                $self->{password},
                { RaiseError => 0, PrintError => 0, AutoCommit => 1 }
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

    if (!defined $self->{instance}) {
        if ($self->connect() == -1) {
            $self->{logger}->writeLogError("Can't connect to the database");
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
    $self->{logger}->writeLogError(<<"EOE");
SQL error: $error (caller: $package:$filename:$line)
Query: $query
EOE
    $self->disconnect();
    $self->{instance} = undef;
}

sub query {
    my $self = shift;
    my $query = shift;
    my (%options) = @_;
    my ($status, $count) = (0, -1);
    my $statement_handle;

    while (1) {
        if (!defined($self->{instance})) {
            $status = $self->connect();
            if ($status != -1) {
                for (my $i = 0; $i < scalar(@{$self->{args}}); $i++) {
                    my $str_quoted = $self->quote(${$self->{args}}[0]);
                    $query =~ s/##__ARG__$i##/$str_quoted/;
                }
                $self->{args} = [];
            }
            if ($status == -1 && $self->{force} != 1) {
                $self->{args} = [];
                last;
            }
        }

        $count++;
        $statement_handle = $self->{instance}->prepare($query);
        if (!defined($statement_handle)) {
            $self->error($self->{instance}->errstr, $query);
            $status = -1;
            last if ($self->{force} == 0 || ($self->{force} == 2 && $count == 1));
            sleep(1);
            next;
        }
        
        if (defined($options{prepare_only})) {
            return ($status, $statement_handle);
        }

        my $rv = $statement_handle->execute;
        if (!$rv) {
            $self->error($statement_handle->errstr, $query);
            $status = -1;
            last if ($self->{force} == 0 || ($self->{force} == 2 && $count == 1));
            sleep(1);
            next;
        }
        last;
    }
    return ($status, $statement_handle);
}

1;
