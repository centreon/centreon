#!/usr/bin/perl

use warnings;
use strict;

centreon::script::dsmd->new()->run();

package centreon::script::dsmd;

use strict;
use warnings;
use centreon::script;
use centreon::common::misc;

use base qw(centreon::script);

use vars qw(%dsmd_config);

my %handlers = (TERM => {}, DIE => {}, HUP => {});

sub new {
    my $class = shift;
    my $self = $class->SUPER::new("dsmd",
        centreon_db_conn => 0,
        centstorage_db_conn => 0,
    );
    bless $self, $class;

    $self->add_options(
        'config-extra=s' => \$self->{opt_extra},
    );

    $self->{whoami} = getpwuid($<);

    %{$self->{dsmd_default_config}} = (
       centreon_user => 'centreon',
       submit_command_timeout => 5,
       macro_config => 'ALARM_ID',
       sql_fetch => 1000,
       clean_locks_time => 3600, # each hours
       clean_locks_keep_stored => 3600,
    );

    $self->{reload} = 0;
    $self->{stop} = 0;
    $self->{last_clean_locks_time} = time();
    $self->set_signal_handlers();
    return $self;
}

sub set_signal_handlers {
    my $self = shift;

    $SIG{TERM} = \&class_handle_TERM;
    $handlers{TERM}->{$self} = sub { $self->handle_TERM() };
    $SIG{__DIE__} = \&class_handle_DIE;
    $handlers{DIE}->{$self} = sub { $self->handle_DIE($_[0]) };
    $SIG{HUP} = \&class_handle_HUP;
    $handlers{HUP}->{$self} = sub { $self->handle_HUP() };
}

sub class_handle_TERM {
    foreach (keys %{$handlers{TERM}}) {
        &{$handlers{TERM}->{$_}}();
    }
}

sub class_handle_DIE {
    my ($msg) = @_;

    foreach (keys %{$handlers{DIE}}) {
        &{$handlers{DIE}->{$_}}($msg);
    }
}

sub class_handle_HUP {
    foreach (keys %{$handlers{HUP}}) {
        &{$handlers{HUP}->{$_}}();
    }
}

sub handle_DIE {
    my ($self, $msg) = @_;

    $self->{logger}->writeLogError($msg);
    $self->{stop} = 1;
}

sub handle_TERM {
    my ($self) = @_;

    $self->{logger}->writeLogInfo("$$ Receiving order to stop...");
    $self->{stop} = 1;
}

sub handle_HUP {
    my ($self) = @_;
    $self->{reload} = 1;
}

sub do_reload {
    my ($self, %options) = @_;

    $self->{logger}->writeLogInfo("Reload in progress...");
    # reopen file
    if ($self->{logger}->is_file_mode()) {
        $self->{logger}->file_mode($self->{logger}->{file_name});
    }
    $self->{logger}->redirect_output();

    centreon::common::misc::reload_db_config($self->{logger}, $self->{config_file}, undef, $self->{db_centstorage});

    $self->{reload} = 0;
}

sub check_signals {
    my ($self, %options) = @_;

    if ($self->{reload} == 1) {
        $self->do_reload();
    }
    if ($self->{stop} == 1) {
        $self->{logger}->writeLogInfo("Quit dsmd");
        exit(0);
    }
}

sub init {
    my ($self, %options) = @_;
    $self->SUPER::init();

    $self->{logger}->writeLogInfo("server launched");

    if (!defined($self->{opt_extra})) {
        $self->{opt_extra} = "/etc/centreon/centreon_dsmd.pm";
    }
    if (-f $self->{opt_extra}) {
        require $self->{opt_extra};
    } else {
        $self->{logger}->writeLogInfo("Can't find extra config file $self->{opt_extra}");
    }
    $self->{dsmd_config} = {%{$self->{dsmd_default_config}}, %dsmd_config};
}

sub add_command {
    my ($self, %options) = @_;
    my $datetime = time();

    my $prefix = "EXTERNALCMD:$options{instance_id}:";
    push @{$self->{submit_cmds}}, $prefix . "[$options{time}] $options{command}";
}

sub submit_split {
    my ($self, %options) = @_;
    my $datetime = time();

    return if ($options{cmd} eq '');

    my $submit;
    if ($self->{whoami} eq $self->{dsmd_config}->{centreon_user}) {
        $options{cmd} =~ s/"/\\"/g;
        $submit = '/bin/echo "' . $options{cmd} . '" >> ' . $self->{cmdDir} . "/" . $datetime . "-dsm";
    } else {
        $options{cmd} =~ s/'/'\\''/g;
        $options{cmd} =~ s/"/\\"/g;
        $submit = "su -l " . $self->{dsmd_config}->{centreon_user} . " -c '/bin/echo \"$options{cmd}\" >> " . $self->{cmdDir} . "/" . $datetime . "-dsm' 2>&1";
    }
    my ($lerror, $stdout) = centreon::common::misc::backtick(
        command => $submit,
        logger => $self->{logger},
        timeout => $self->{dsmd_config}->{submit_command_timeout}
    );

    $self->{logger}->writeLogInfo("SUBMIT: Force service status via passive check update");
    $self->{logger}->writeLogInfo("SUBMIT: Launched command: $submit");
    if (defined($stdout) && $stdout ne "") {
        $self->{logger}->writeLogError("SUBMIT RESULT stdout: $stdout");
    }
}

sub submit_commands {
    my ($self, %options) = @_;
    my $datetime = time();

    my $i = 0;
    my $str = '';
    while ((my $cmd = shift @{$self->{submit_cmds}})) {
        $i++;
        $str .= $cmd . "\n";
        if ($i % 20 != 0) {
            next;
        }

        $self->submit_split(cmd => $str);
        $str = '';
    }
    $self->submit_split(cmd => $str);

    $self->{submit_cmds} = [];
}

sub load_slot_locks {
    my ($self, %options) = @_;

    $self->{cache_locks} = {};
    my $rows = [];
    while (1) {
        my ($status, $sth) = $self->{db_centstorage}->query("SELECT `lock_id`, `host_id`, `service_id`, `internal_id`, `id`, `ctime`, `status` FROM mod_dsm_locks");
        if ($status != -1) {
            while (my $row = ( shift(@$rows) || # get row from cache, or reload cache:
                       shift(@{$rows = $sth->fetchall_arrayref(undef, $self->{dsmd_config}->{sql_fetch})||[]})) ) {
                $self->{cache_locks}->{$$row[1] . '.' . $$row[2]} = [$$row[0], $$row[3], $$row[4], $$row[5], $$row[6]];
            }
            last;
        }

        $self->{logger}->writeLogError("Cannot load locks table");
        $self->check_signals();
        sleep(1);
    }
}

sub get_alarms {
    my ($self, %options) = @_;

    $self->{current_alarms} = [];
    $self->{current_pools_status} = {};

    # if hosts is disabled: it's an host on the wrong instance or instance is not running. otherwise we care about enabled only.
    my ($status, $sth) = $self->{db_centstorage}->query(
        "SELECT mdc.`cache_id`, mdc.`host_id`, mdc.`ctime`, mdc.`status`, mdc.`pool_prefix`, mdc.`id`, mdc.`macros`, mdc.`output`
         FROM mod_dsm_cache mdc, hosts
         WHERE mdc.host_id = hosts.host_id AND
               hosts.enabled = '1'
         "
    );
    if ($status == -1) {
        $self->{logger}->writeLogError('cannot get alarms');
        return 1;
    }

    my $rows = [];
    my @sql_where = ();
    while (my $row = ( shift(@$rows) || # get row from cache, or reload cache:
                       shift(@{$rows = $sth->fetchall_arrayref(undef, $self->{dsmd_config}->{sql_fetch})||[]})) ) {
        push @{$self->{current_alarms}}, $row;
        push @sql_where, "(services.host_id = $row->[1] AND services.description LIKE " . $self->{db_centstorage}->quote($row->[4] . '%') . ")";
    }

    return 1 if (scalar(@{$self->{current_alarms}}) == 0);

    ($status, $sth) = $self->{db_centstorage}->query(
        "SELECT hosts.`name`, hosts.`instance_id`, services.`host_id`, services.`service_id`, services.`description`, services.`last_check`, services.`state`, cv.`value` FROM services " .
        "LEFT JOIN customvariables cv ON cv.host_id = services.host_id AND cv.service_id = services.service_id AND cv.name = '" . $self->{dsmd_config}->{macro_config} . "', hosts " .
        "WHERE (" . join('OR', @sql_where) . ") AND services.enabled = '1' AND services.host_id = hosts.host_id"
    );
    if ($status == -1) {
        $self->{logger}->writeLogError("Cannot get alarms");
        return 1;
    }

    $rows = [];
    while (my $row = ( shift(@$rows) || # get row from cache, or reload cache:
                       shift(@{$rows = $sth->fetchall_arrayref(undef, $self->{dsmd_config}->{sql_fetch})||[]})) ) {
        $self->{current_pools_status}->{$row->[2]} = {} if (!defined($self->{current_pools_status}->{$row->[2]}));
        $self->{current_pools_status}->{$row->[2]}->{$row->[4]} = {
            host_name => $row->[0],
            instance_id => $row->[1],
            service_id => $row->[3],
            last_check => $row->[5],
            state => $row->[6],
            alarm_id => $row->[7]
        };
    }

    return 0;
}

sub find_slot {
    my ($self, %options) = @_;
    my $free_slot = defined($options{free_slot}) && $options{free_slot} == 1 ? 1 : 0;

    if (!defined($self->{current_pools_status}->{$options{host_id}})) {
        $self->{logger}->writeLogError("[find_slot_id] cannot find pool prefix [host id = $options{host_id}]");
        return (-2);
    }

    my $alarm_id = '';
    $alarm_id = $1 if ($options{alarm_id} =~ /^\d+##(.+)/);
    my ($free_slot_service, $free_slot_data) = (undef, undef);
    foreach my $service_description (sort keys %{$self->{current_pools_status}->{$options{host_id}}}) {
        next if ($service_description !~ /^$options{pool_prefix}.*/);

        if (defined($self->{cache_locks}->{$options{host_id} . '.' . $self->{current_pools_status}->{$options{host_id}}->{$service_description}->{service_id}})) {
            # Look if it's the alarm is on database now
            my $cache_alarm_id = $self->{cache_locks}->{$options{host_id} . '.' . $self->{current_pools_status}->{$options{host_id}}->{$service_description}->{service_id}}->[1] .
                '##' . (defined($self->{cache_locks}->{$options{host_id} . '.' . $self->{current_pools_status}->{$options{host_id}}->{$service_description}->{service_id}}->[2]) ? $self->{cache_locks}->{$options{host_id} . '.' . $self->{current_pools_status}->{$options{host_id}}->{$service_description}->{service_id}}->[2] : '');

            # The custom ALARM_ID can happen before the service status change. So we need to check the status.
            if ($cache_alarm_id eq $self->{current_pools_status}->{$options{host_id}}->{$service_description}->{alarm_id} &&
                $self->{current_pools_status}->{$options{host_id}}->{$service_description}->{state} == $self->{cache_locks}->{$options{host_id} . '.' . $self->{current_pools_status}->{$options{host_id}}->{$service_description}->{service_id}}->[4]) {
                $self->{logger}->writeLogInfo("[find_slot_id] delete lock entry [host id = $options{host_id}] [service = $service_description]");
                $self->delete_locks(id => $options{host_id} . '.' . $self->{current_pools_status}->{$options{host_id}}->{$service_description}->{service_id},
                                    lock_id => $self->{cache_locks}->{$options{host_id} . '.' . $self->{current_pools_status}->{$options{host_id}}->{$service_description}->{service_id}}->[0]);
                if ($free_slot == 1 && !defined($free_slot_data) && ($self->{current_pools_status}->{$options{host_id}}->{$service_description}->{state} == 0 ||
                    $self->{current_pools_status}->{$options{host_id}}->{$service_description}->{state} == 4)) {
                    $free_slot_service = $service_description;
                    $free_slot_data = $self->{current_pools_status}->{$options{host_id}}->{$service_description};
                }
            } elsif ($alarm_id ne '' && $cache_alarm_id =~ /##\Q$alarm_id\E$/) {
                return (1, $service_description, $self->{current_pools_status}->{$options{host_id}}->{$service_description});
            }
        } elsif ($free_slot == 1 && !defined($free_slot_data) &
                 ($self->{current_pools_status}->{$options{host_id}}->{$service_description}->{state} == 0 ||
                  $self->{current_pools_status}->{$options{host_id}}->{$service_description}->{state} == 4)) { # slot is not in lock table and status is OK/PENDING
            $free_slot_service = $service_description;
            $free_slot_data = $self->{current_pools_status}->{$options{host_id}}->{$service_description};
        }

        if ($alarm_id ne '' && $self->{current_pools_status}->{$options{host_id}}->{$service_description}->{alarm_id} =~ /##\Q$alarm_id\E$/) {
            return (0, $service_description, $self->{current_pools_status}->{$options{host_id}}->{$service_description});
        }
    }

    if ($free_slot == 1) {
        return (0, undef, undef, $free_slot_service, $free_slot_data);
    }
    $self->{logger}->writeLogError("[find_slot_id] cannot find slot [alarm id = $options{alarm_id}] [host id = $options{host_id}] [pool prefix = $options{pool_prefix}]");
    return (-1);
}

sub insert_history {
    my ($self, %options) = @_;

    $self->{db_centstorage}->query("INSERT INTO mod_dsm_history (`host_id`, `service_id`, `ctime`, `status`, `internal_id`, `id`, `macros`, `output`) VALUES (" .
        $self->{db_centstorage}->quote($options{alarm}->[1]) . ',' . $self->{db_centstorage}->quote($options{service_id}) . ',' .
        $self->{db_centstorage}->quote($options{alarm}->[2]) . ',' . $self->{db_centstorage}->quote($options{alarm}->[3]) . ',' .
        $self->{db_centstorage}->quote($options{alarm}->[0]) . ',' . $self->{db_centstorage}->quote($options{alarm}->[5]) . ',' .
        $self->{db_centstorage}->quote($options{alarm}->[6]) . ',' . $self->{db_centstorage}->quote($options{alarm}->[7]) .
    ')');
}

sub clean_locks {
    my ($self, %options) = @_;

    return if ((time() - $self->{last_clean_locks_time}) < $self->{dsmd_config}->{clean_locks_time});
    my $current_time = time();
    $self->{logger}->writeLogInfo("clean locks checked");
    foreach (keys %{$self->{cache_locks}}) {
        if (($current_time - $self->{cache_locks}->{$_}->[3]) > $self->{dsmd_config}->{clean_locks_keep_stored}) {
            $self->delete_locks(
                id => $_,
                lock_id => $self->{cache_locks}->{$_}->[0]
            );
        }
    }
    $self->{last_clean_locks_time} = time();
}

sub delete_alarms {
    my ($self, %options) = @_;

    $self->{db_centstorage}->query("DELETE FROM mod_dsm_cache WHERE cache_id = " . $options{alarm}->[0]);
    $self->insert_history(alarm => $options{alarm});
}

sub check_cache {
    my ($self, %options) = @_;

    foreach my $alarm (@{$self->{current_alarms}}) {
        # 0 = cache_id, 1 = host_id, 2 = ctime, 3 = status, 4 = pool_prefix, 5 = id, 6 = macros, 7 = output
        if ($alarm->[0] < $options{alarm}->[0] && $alarm->[5] =~ /^$options{alarm}->[5]$/ &&
            $alarm->[1] == $options{alarm}->[1] && $alarm->[4] =~ /^$options{alarm}->[4]$/ &&
            $alarm->[3] != $options{alarm}->[3]) {
            return 0;
        } elsif ($alarm->[0] >= $options{alarm}->[0]) {
            last;
        }
    }
    return 1;
}

sub delete_locks {
    my ($self, %options) = @_;

    my ($status) = $self->{db_centstorage}->query("DELETE FROM mod_dsm_locks WHERE lock_id = " . $options{lock_id});
    return if ($status == -1);
    delete $self->{cache_locks}->{$options{id}};
}

sub insert_locks {
    my ($self, %options) = @_;

    my $ctime = time();
    $self->{db_centstorage}->query("INSERT INTO mod_dsm_locks (host_id, service_id, internal_id, `id`, ctime, `status`) VALUES (" .
        $self->{db_centstorage}->quote($options{alarm}->[1]) . ',' . $self->{db_centstorage}->quote($options{service_id}) . ',' .
        $self->{db_centstorage}->quote($options{alarm}->[0]) . ',' . $self->{db_centstorage}->quote($options{alarm}->[5]) . ',' .
        $ctime . ',' . $self->{db_centstorage}->quote($options{alarm}->[3]) .
    ')');
    $self->{cache_locks}->{$options{alarm}->[1] . '.' . $options{service_id}} =
        [$self->{db_centstorage}->last_insert_id(), $options{alarm}->[0], $options{alarm}->[5], $ctime, $options{alarm}->[3]];
}

sub custom_macros {
    my ($self, %options) = @_;

    return if (!defined($options{macros}) || $options{macros} eq '');
    foreach (split /\|/, $options{macros}) {
        if (/^(.*?)=(.*)/) {
            $self->add_command(
                instance_id => $options{instance_id},
                time => $options{time},
                command => "CHANGE_CUSTOM_SVC_VAR;$options{host_name};$options{service_description};$1;$2"
            );
        }
    }
}

sub manage_alarm_ok {
    my ($self, %options) = @_;

    # if we use an ID for the alarms, there is the table mod_dsm_locks.
    # If an user forces the slot to OK (submit result), locks are cleaned each hour (by default). So the slot could be used only after the cleaning.

    # Ok without ID, we ignore it
    if (!defined($options{alarm}->[5]) || $options{alarm}->[5] eq '') {
        $self->delete_alarms(alarm => $options{alarm});
        return ;
    }

    my ($status, $service_description, $data) =
        $self->find_slot(alarm_id => $options{alarm_id}, host_id => $options{alarm}->[1], pool_prefix => $options{alarm}->[4]);
    $self->{logger}->writeLogDebug("find slot result ok [alarm id = $options{alarm_id}] [host id = $options{alarm}->[1]] [service = $service_description] -> [status = $status] [service desc: $service_description] [data: $data]");
    if ($status == 1) {
        $self->{logger}->writeLogInfo("find slot id [alarm id = $options{alarm_id}] [host id = $options{alarm}->[1]] [service = $service_description]: already an alarm in locks. Need to wait.");
        return ;
    }
    if ($status < 0) {
        if ($self->check_cache(alarm => $options{alarm})) {
            $self->delete_alarms(alarm => $options{alarm});
        }
        return ;
    }

    # OK and PENDING
    $self->delete_alarms(alarm => $options{alarm}, service_id => $data->{service_id});
    if ($data->{state} == 0 || $data->{state} == 4) {
        $self->{logger}->writeLogInfo("find slot id [alarm id = $options{alarm_id}] [host id = $options{alarm}->[1]] [service = $service_description]: status is already OK/PENDING");
        return ;
    }

    if ($data->{last_check} > $options{alarm}->[2]) {
        $self->{logger}->writeLogInfo("find slot id [alarm id = $options{alarm_id}] [host id = $options{alarm}->[1]] [service = $service_description]: update time is too old");
        return ;
    }

    $self->insert_locks(alarm => $options{alarm}, service_id => $data->{service_id});
    my $output = defined($options{alarm}->[7]) ? $options{alarm}->[7] : 'Free Slot';
    $self->add_command(instance_id => $data->{instance_id}, time => $options{alarm}->[2], command => "PROCESS_SERVICE_CHECK_RESULT;$data->{host_name};$service_description;0;$output");
    $self->add_command(instance_id => $data->{instance_id}, time => $options{alarm}->[2], command => "CHANGE_CUSTOM_SVC_VAR;$data->{host_name};$service_description;$self->{dsmd_config}->{macro_config};$options{alarm_id}");
    $self->custom_macros(
        instance_id => $data->{instance_id},
        time => $options{alarm}->[2],
        host_name => $data->{host_name},
        service_description => $service_description,
        macros => $options{alarm}->[6]
    );
}

sub manage_alarm_error {
    my ($self, %options) = @_;

    my ($status, $service_description, $data, $free_slot_service, $free_slot_data) =
        $self->find_slot(alarm_id => $options{alarm_id}, host_id => $options{alarm}->[1], pool_prefix => $options{alarm}->[4], free_slot => 1);
    $self->{logger}->writeLogDebug("find slot result error [alarm id = $options{alarm_id}] [host id = $options{alarm}->[1]] [service = $service_description] -> [status = $status] [service desc: $service_description] [data: $data] [free slot service: $free_slot_service] [free slot data: $free_slot_data]");
    if ($status == 1) {
        $self->{logger}->writeLogInfo("find slot id [alarm id = $options{alarm_id}] [host id = $options{alarm}->[1]] [service = $service_description]: already an alarm in locks. Need to wait.");
        return ;
    }
    if ($status == -1) {
        return ;
    }

    my $data2 = defined($data) ? $data : $free_slot_data;
    my $service2 = defined($service_description) ? $service_description : $free_slot_service;
    return if (!defined($data2));

    $self->insert_locks(alarm => $options{alarm}, service_id => $data2->{service_id});
    $self->delete_alarms(alarm => $options{alarm});
    my $output = defined($options{alarm}->[7]) ? $options{alarm}->[7] : '';
    $self->add_command(instance_id => $data2->{instance_id}, time => $options{alarm}->[2], command => "PROCESS_SERVICE_CHECK_RESULT;$data2->{host_name};$service2;$options{alarm}->[3];$output");
    $self->add_command(instance_id => $data2->{instance_id}, time => $options{alarm}->[2], command => "CHANGE_CUSTOM_SVC_VAR;$data2->{host_name};$service2;$self->{dsmd_config}->{macro_config};$options{alarm_id}");
    $self->custom_macros(
        instance_id => $data->{instance_id},
        time => $options{alarm}->[2],
        host_name => $data2->{host_name},
        service_description => $service2,
        macros => $options{alarm}->[6]
    );
}

sub manage_alarms {
    my ($self, %options) = @_;

    $self->{submit_cmds} = [];
    $self->{add_locks} = {};
    $self->{db_centstorage}->transaction_mode(1);
    eval {
        foreach my $alarm (@{$self->{current_alarms}}) {
            # 0 = cache_id, 1 = host_id, 2 = ctime, 3 = status, 4 = pool_prefix, 5 = id, 6 = macros, 7 = output
            my $alarm_id = $alarm->[0] . "##" . (defined($alarm->[5]) ? $alarm->[5] : '');

            # Alarm Ok
            if ($alarm->[3] == 0) {
                $self->manage_alarm_ok(alarm => $alarm, alarm_id => $alarm_id);
                next;
            }

            # Alarm Error
            $self->manage_alarm_error(alarm => $alarm, alarm_id => $alarm_id);
        }

        $self->{db_centstorage}->commit;
        $self->{cache_locks} = { %{$self->{cache_locks}}, %{$self->{add_locks}} };
        $self->submit_commands();
    };
    if ($@) {
        $self->{db_centstorage}->rollback;
    }
    $self->{db_centstorage}->transaction_mode(0);
}

sub run {
    my ($self, %options) = @_;

    $self->SUPER::run();
    $self->{cmdDir} = $self->{centreon_config}->{VarLib} . "/centcore";

    $self->{db_centreon} = centreon::common::db->new(
        db => $self->{centreon_config}->{centreon_db},
        host => $self->{centreon_config}->{db_host},
        port => $self->{centreon_config}->{db_port},
        user => $self->{centreon_config}->{db_user},
        password => $self->{centreon_config}->{db_passwd},
        force => 0,
        logger => $self->{logger}
    );
    $self->{db_centreon}->connect();

    $self->{db_centstorage} = centreon::common::db->new(
        db => $self->{centreon_config}->{centstorage_db},
        host => $self->{centreon_config}->{db_host},
        port => $self->{centreon_config}->{db_port},
        user => $self->{centreon_config}->{db_user},
        password => $self->{centreon_config}->{db_passwd},
        force => 0,
        logger => $self->{logger}
    );
    $self->{db_centstorage}->connect();

    my $moduleDsm = $self->{db_centreon}->query("
        SELECT count(name) FROM modules_informations WHERE name = 'centreon-dsm'
    ");

    if ($moduleDsm->fetchrow() == 0) {
        $self->{logger}->writeLogInfo('Module is not installed, exiting program....');
        exit(1);
    }

    $self->load_slot_locks();

    while (1) {
        $self->check_signals();
        $self->clean_locks();
        if ($self->get_alarms() == 0) {
            $self->manage_alarms();
        }

        sleep(1);
    }
}

__END__

=head1 NAME

dsmd.pl - centreon dsm daemon to manage events

=head1 SYNOPSIS

dsmd.pl [options]

=head1 OPTIONS

=over 8

=item B<--config>

Specify the path to the main configuration file (default: /etc/centreon/conf.pm).

=item B<--help>

Print a brief help message and exits.

=back

=head1 DESCRIPTION

B<dsmd.pl>.

=cut


