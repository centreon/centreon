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

package gorgone::modules::centreon::mbi::etl::class;

use base qw(gorgone::class::module);

use strict;
use warnings;
use gorgone::standard::library;
use gorgone::standard::constants qw(:all);
use gorgone::class::sqlquery;
use gorgone::class::http::http;
use XML::LibXML::Simple;
use JSON::XS;
use gorgone::modules::centreon::mbi::libs::Messages;
use gorgone::modules::centreon::mbi::etl::import::main;
use gorgone::modules::centreon::mbi::etl::event::main;
use gorgone::modules::centreon::mbi::etl::perfdata::main;
use gorgone::modules::centreon::mbi::libs::centreon::ETLProperties;
use Try::Tiny;
use EV;

use constant NONE => 0;
use constant RUNNING => 1;
use constant STOP => 2;

use constant NOTDONE => 0;
use constant DONE => 1;

use constant UNPLANNED => -1;
use constant PLANNED => 0;
#use constant RUNNING => 1;
use constant FINISHED => 2;

my %handlers = (TERM => {}, HUP => {});
my ($connector);

sub new {
    my ($class, %options) = @_;
    $connector = $class->SUPER::new(%options);
    bless $connector, $class;

    $connector->{cbis_profile} = (defined($connector->{config}->{cbis_profile}) && $connector->{config}->{cbis_profile} ne '') ?
        $connector->{config}->{cbis_profile} : '/etc/centreon-bi/cbis-profile.xml';
    $connector->{reports_profile} = (defined($connector->{config}->{reports_profile}) && $connector->{config}->{reports_profile} ne '') ?
        $connector->{config}->{reports_profile} : '/etc/centreon-bi/reports-profile.xml';

    $connector->{run} = { status => NONE };

    $connector->set_signal_handlers();
    return $connector;
}

sub set_signal_handlers {
    my $self = shift;

    $SIG{TERM} = \&class_handle_TERM;
    $handlers{TERM}->{$self} = sub { $self->handle_TERM() };
    $SIG{HUP} = \&class_handle_HUP;
    $handlers{HUP}->{$self} = sub { $self->handle_HUP() };
}

sub handle_HUP {
    my $self = shift;
    $self->{reload} = 0;
}

sub handle_TERM {
    my $self = shift;
    $self->{logger}->writeLogDebug("[nodes] $$ Receiving order to stop...");
    $self->{stop} = 1;
}

sub class_handle_TERM {
    foreach (keys %{$handlers{TERM}}) {
        &{$handlers{TERM}->{$_}}();
    }
}

sub class_handle_HUP {
    foreach (keys %{$handlers{HUP}}) {
        &{$handlers{HUP}->{$_}}();
    }
}

sub reset {
    my ($self, %options) = @_;

    $self->{run} = { status => NONE };
}

sub runko {
    my ($self, %options) = @_;

    $self->send_log(
        code => GORGONE_ACTION_FINISH_KO,
        token => defined($options{token}) ? $options{token} : $self->{run}->{token},
        data => {
            messages => [ ['E', $options{msg} ] ]
        }
    );

    $self->check_stopped_ko();
    return 1;
}

sub db_parse_xml {
    my ($self, %options) = @_;

    my ($rv, $message, $content) = gorgone::standard::misc::slurp(file => $options{file});
    return (0, $message) if (!$rv);
    eval {
        $SIG{__WARN__} = sub {};
        $content = XMLin($content, ForceArray => [], KeyAttr => []);
    };
    if ($@) {
        die 'cannot read xml file: ' . $@;
    }

    my $dbcon = {};
    if (!defined($content->{profile})) {
        die 'no profile';
    }
    foreach my $profile (@{$content->{profile}}) {
        my $name = lc($profile->{name});
        $name =~ s/censtorage/centstorage/;
        $dbcon->{$name} = { port => 3306 };
        foreach my $prop (@{$profile->{baseproperties}->{property}}) {
            if ($prop->{name} eq 'odaURL' && $prop->{value} =~ /jdbc\:[a-z]+\:\/\/([^:]*)(\:\d+)?\/(.*)/) {
                $dbcon->{$name}->{host} = $1;
                $dbcon->{$name}->{db} = $3;
                if (defined($2) && $2 ne '') {
                    $dbcon->{$name}->{port} = $2;
                    $dbcon->{$name}->{port} =~ s/\://;
                }
                $dbcon->{$name}->{db} =~ s/\?autoReconnect\=true//;
            } elsif ($prop->{name} eq 'odaUser') {
                $dbcon->{$name}->{user} = $prop->{value};
            } elsif ($prop->{name} eq 'odaPassword') {
                $dbcon->{$name}->{password} = $prop->{value};
            }
        }
    }
    foreach my $profile ('centreon', 'centstorage') {
        die 'cannot find profile ' . $profile if (!defined($dbcon->{$profile}));
        foreach ('host', 'db', 'port', 'user', 'password') {
            die "property $_ for profile $profile must be defined"
                if (!defined($dbcon->{$profile}->{$_}) || $dbcon->{$profile}->{$_} eq '');
        }
    }

    return $dbcon;
}

sub execute_action {
    my ($self, %options) = @_;

    $self->send_internal_action({
        action => 'ADDLISTENER',
        data => [
            {
                identity => 'gorgone-' . $self->{module_id},
                event => 'CENTREONMBIETLLISTENER',
                token => $self->{module_id} . '-' . $self->{run}->{token} . '-' . $options{substep},
                timeout => 43200
            }
        ]
    });

    my $content =  {
        dbmon => $self->{run}->{dbmon},
        dbbi => $self->{run}->{dbbi},
        params => $options{params}
    };
    if (defined($options{etlProperties})) {
        $content->{etlProperties} = $self->{run}->{etlProperties};
    }
    if (defined($options{dataRetention})) {
        $content->{dataRetention} = $self->{run}->{dataRetention};
    }
    if (defined($options{options})) {
        $content->{options} = $self->{run}->{options};
    }

    $self->send_internal_action({
        action => $options{action},
        token => $self->{module_id} . '-' . $self->{run}->{token} . '-' . $options{substep},
        data => {
            instant => 1,
            content => $content
        }
    });
}

sub watch_etl_event {
    my ($self, %options) = @_;

    if (defined($options{indexes})) {
        $self->{run}->{schedule}->{event}->{substeps_executed}++;
        my ($idx, $idx2) = split(/-/, $options{indexes});
        $self->{run}->{schedule}->{event}->{stages}->[$idx]->[$idx2]->{status} = FINISHED;
    }

    return if (!$self->check_stopped_ko());

    if ($self->{run}->{schedule}->{event}->{substeps_executed} >= $self->{run}->{schedule}->{event}->{substeps_total}) {
        $self->send_log(code => GORGONE_MODULE_CENTREON_MBIETL_PROGRESS, token => $self->{run}->{token}, data => { messages => [ ['I', '[SCHEDULER][EVENT] <<<<<<< end'] ] });
        $self->{run}->{schedule}->{event}->{status} = FINISHED;
        $self->check_stopped_ok();
        return ;
    }

    my $stage = $self->{run}->{schedule}->{event}->{current_stage};
    my $stage_finished = 0;
    while ($stage <= 2) {
        while (my ($idx, $val) = each(@{$self->{run}->{schedule}->{event}->{stages}->[$stage]})) {
            if (!defined($val->{status})) {
                $self->{logger}->writeLogDebug("[mbi-etl] execute substep event-$stage-$idx");
                $self->{run}->{schedule}->{event}->{substeps_execute}++;
                $self->execute_action(
                    action => 'CENTREONMBIETLWORKERSEVENT',
                    substep => "event-$stage-$idx",
                    etlProperties => 1,
                    options => 1,
                    params => $self->{run}->{schedule}->{event}->{stages}->[$stage]->[$idx]
                );
                $self->{run}->{schedule}->{event}->{stages}->[$stage]->[$idx]->{status} = RUNNING;
            } elsif ($val->{status} == FINISHED) {
                $stage_finished++;
            }
        }

        if ($stage_finished >= scalar(@{$self->{run}->{schedule}->{event}->{stages}->[$stage]})) {
            $self->{run}->{schedule}->{event}->{current_stage}++;
            $stage = $self->{run}->{schedule}->{event}->{current_stage};
        } else {
            last;
        }
    }
}

sub watch_etl_perfdata {
    my ($self, %options) = @_;

    if (defined($options{indexes})) {
        $self->{run}->{schedule}->{perfdata}->{substeps_executed}++;
        my ($idx, $idx2) = split(/-/, $options{indexes});
        $self->{run}->{schedule}->{perfdata}->{stages}->[$idx]->[$idx2]->{status} = FINISHED;
    }

    return if (!$self->check_stopped_ko());

    if ($self->{run}->{schedule}->{perfdata}->{substeps_executed} >= $self->{run}->{schedule}->{perfdata}->{substeps_total}) {
        $self->send_log(code => GORGONE_MODULE_CENTREON_MBIETL_PROGRESS, token => $self->{run}->{token}, data => { messages => [ ['I', '[SCHEDULER][PERFDATA] <<<<<<< end'] ] });
        $self->{run}->{schedule}->{perfdata}->{status} = FINISHED;
        $self->check_stopped_ok();
        return ;
    }

    my $stage = $self->{run}->{schedule}->{perfdata}->{current_stage};
    my $stage_finished = 0;
    while ($stage <= 2) {
        while (my ($idx, $val) = each(@{$self->{run}->{schedule}->{perfdata}->{stages}->[$stage]})) {
            if (!defined($val->{status})) {
                $self->{logger}->writeLogDebug("[mbi-etl] execute substep perfdata-$stage-$idx");
                $self->{run}->{schedule}->{perfdata}->{substeps_execute}++;
                $self->execute_action(
                    action => 'CENTREONMBIETLWORKERSPERFDATA',
                    substep => "perfdata-$stage-$idx",
                    etlProperties => 1,
                    options => 1,
                    params => $self->{run}->{schedule}->{perfdata}->{stages}->[$stage]->[$idx]
                );
                $self->{run}->{schedule}->{perfdata}->{stages}->[$stage]->[$idx]->{status} = RUNNING;
            } elsif ($val->{status} == FINISHED) {
                $stage_finished++;
            }
        }

        if ($stage_finished >= scalar(@{$self->{run}->{schedule}->{perfdata}->{stages}->[$stage]})) {
            $self->{run}->{schedule}->{perfdata}->{current_stage}++;
            $stage = $self->{run}->{schedule}->{perfdata}->{current_stage};
        } else {
            last;
        }
    }
}

sub watch_etl_dimensions {
    my ($self, %options) = @_;

    if (defined($options{indexes})) {
        $self->{run}->{schedule}->{dimensions}->{substeps_executed}++;
    }

    return if (!$self->check_stopped_ko());

    if ($self->{run}->{schedule}->{dimensions}->{substeps_executed} >= $self->{run}->{schedule}->{dimensions}->{substeps_total}) {
        $self->send_log(code => GORGONE_MODULE_CENTREON_MBIETL_PROGRESS, token => $self->{run}->{token}, data => { messages => [ ['I', '[SCHEDULER][DIMENSIONS] <<<<<<< end'] ] });
        $self->{run}->{schedule}->{dimensions}->{status} = FINISHED;
        $self->run_etl();
        $self->check_stopped_ok();
        return ;
    }

    $self->{run}->{schedule}->{dimensions}->{substeps_execute}++;
    $self->execute_action(
        action => 'CENTREONMBIETLWORKERSDIMENSIONS',
        substep => 'dimensions-1',
        etlProperties => 1,
        options => 1,
        params => {}
    );
}

sub watch_etl_import {
    my ($self, %options) = @_;

    if (defined($options{indexes})) {
        $self->{run}->{schedule}->{import}->{substeps_executed}++;
        my ($idx, $idx2) = split(/-/, $options{indexes});
        if (defined($idx) && defined($idx2)) {
            $self->{run}->{schedule}->{import}->{actions}->[$idx]->{actions}->[$idx2]->{status} = FINISHED;
        } else {
            $self->{run}->{schedule}->{import}->{actions}->[$idx]->{status} = FINISHED;
        }
    }

    return if (!$self->check_stopped_ko());

    if ($self->{run}->{schedule}->{import}->{substeps_executed} >= $self->{run}->{schedule}->{import}->{substeps_total}) {
        $self->send_log(code => GORGONE_MODULE_CENTREON_MBIETL_PROGRESS, token => $self->{run}->{token}, data => { messages => [ ['I', '[SCHEDULER][IMPORT] <<<<<<< end'] ] });
        $self->{run}->{schedule}->{import}->{status} = FINISHED;
        $self->run_etl();
        $self->check_stopped_ok();
        return ;
    }

    while (my ($idx, $val) = each(@{$self->{run}->{schedule}->{import}->{actions}})) {
        if (!defined($val->{status})) {
            $self->{logger}->writeLogDebug("[mbi-etl] execute substep import-$idx");
            $self->{run}->{schedule}->{import}->{substeps_execute}++;
            $self->{run}->{schedule}->{import}->{actions}->[$idx]->{status} = RUNNING;
            $self->execute_action(
                action => 'CENTREONMBIETLWORKERSIMPORT',
                substep => "import-$idx",
                params => {
                    type => $val->{type}, 
                    db => $val->{db},
                    sql => $val->{sql},
                    command => $val->{command},
                    message => $val->{message}
                }
            );
        } elsif ($val->{status} == FINISHED) {
            while (my ($idx2, $val2) = each(@{$val->{actions}})) {
                next if (defined($val2->{status}));

                $self->{logger}->writeLogDebug("[mbi-etl] execute substep import-$idx-$idx2");
                $self->{run}->{schedule}->{import}->{substeps_execute}++;
                $self->{run}->{schedule}->{import}->{actions}->[$idx]->{actions}->[$idx2]->{status} = RUNNING;
                $self->execute_action(
                    action => 'CENTREONMBIETLWORKERSIMPORT',
                    substep => "import-$idx-$idx2",
                    params => $val2
                );
            }
        }        
    }
}

sub run_etl_import {
    my ($self, %options) = @_;

    if ((defined($self->{run}->{etlProperties}->{'host.dedicated'}) && $self->{run}->{etlProperties}->{'host.dedicated'} eq 'false')
        || ($self->{run}->{dbbi}->{centstorage}->{host} . ':' . $self->{run}->{dbbi}->{centstorage}->{port} eq $self->{run}->{dbmon}->{centstorage}->{host} . ':' . $self->{run}->{dbmon}->{centstorage}->{port})
        || ($self->{run}->{dbbi}->{centreon}->{host} . ':' . $self->{run}->{dbbi}->{centreon}->{port} eq $self->{run}->{dbmon}->{centreon}->{host} . ':' . $self->{run}->{dbmon}->{centreon}->{port})) {
        die 'Do not execute this script if the reporting engine is installed on the monitoring server. In case of "all in one" installation, do not consider this message';
    }

    $self->send_log(code => GORGONE_MODULE_CENTREON_MBIETL_PROGRESS, token => $self->{run}->{token}, data => { messages => [ ['I', '[SCHEDULER][IMPORT] >>>>>>> start' ] ] });

    gorgone::modules::centreon::mbi::etl::import::main::prepare($self);

    $self->{run}->{schedule}->{import}->{status} = RUNNING;

    $self->{run}->{schedule}->{import}->{substeps_execute} = 0;
    $self->{run}->{schedule}->{import}->{substeps_executed} = 0;
    $self->{run}->{schedule}->{import}->{substeps_total} = 0;
    foreach (@{$self->{run}->{schedule}->{import}->{actions}}) {
        $self->{run}->{schedule}->{import}->{substeps_total}++;
        my $num = defined($_->{actions}) ? scalar(@{$_->{actions}}) : 0;
        $self->{run}->{schedule}->{import}->{substeps_total} += $num if ($num > 0);
    }

    $self->{logger}->writeLogDebug("[mbi-etl] import substeps " . $self->{run}->{schedule}->{import}->{substeps_total});

    $self->watch_etl_import();
}

sub run_etl_dimensions {
    my ($self, %options) = @_;

    $self->send_log(code => GORGONE_MODULE_CENTREON_MBIETL_PROGRESS, token => $self->{run}->{token}, data => { messages => [ ['I', '[SCHEDULER][DIMENSIONS] >>>>>>> start' ] ] });
    $self->{run}->{schedule}->{dimensions}->{status} = RUNNING;
    $self->{run}->{schedule}->{dimensions}->{substeps_execute} = 0;
    $self->{run}->{schedule}->{dimensions}->{substeps_executed} = 0;
    $self->{run}->{schedule}->{dimensions}->{substeps_total} = 1;
    $self->watch_etl_dimensions();
}

sub run_etl_event {
    my ($self, %options) = @_;

    $self->send_log(code => GORGONE_MODULE_CENTREON_MBIETL_PROGRESS, token => $self->{run}->{token}, data => { messages => [ ['I', '[SCHEDULER][EVENT] >>>>>>> start' ] ] });

    gorgone::modules::centreon::mbi::etl::event::main::prepare($self);

    $self->{run}->{schedule}->{event}->{status} = RUNNING;
    $self->{run}->{schedule}->{event}->{current_stage} = 0;
    $self->{run}->{schedule}->{event}->{substeps_execute} = 0;
    $self->{run}->{schedule}->{event}->{substeps_executed} = 0;
    $self->{run}->{schedule}->{event}->{substeps_total} = 
        scalar(@{$self->{run}->{schedule}->{event}->{stages}->[0]}) + scalar(@{$self->{run}->{schedule}->{event}->{stages}->[1]}) + scalar(@{$self->{run}->{schedule}->{event}->{stages}->[2]});

    $self->{logger}->writeLogDebug("[mbi-etl] event substeps " . $self->{run}->{schedule}->{event}->{substeps_total});

    $self->watch_etl_event();
}

sub run_etl_perfdata {
    my ($self, %options) = @_;

    $self->send_log(code => GORGONE_MODULE_CENTREON_MBIETL_PROGRESS, token => $self->{run}->{token}, data => { messages => [ ['I', '[SCHEDULER][PERFDATA] >>>>>>> start' ] ] });

    gorgone::modules::centreon::mbi::etl::perfdata::main::prepare($self);

    $self->{run}->{schedule}->{perfdata}->{status} = RUNNING;
    $self->{run}->{schedule}->{perfdata}->{current_stage} = 0;
    $self->{run}->{schedule}->{perfdata}->{substeps_execute} = 0;
    $self->{run}->{schedule}->{perfdata}->{substeps_executed} = 0;
    $self->{run}->{schedule}->{perfdata}->{substeps_total} = 
        scalar(@{$self->{run}->{schedule}->{perfdata}->{stages}->[0]}) + scalar(@{$self->{run}->{schedule}->{perfdata}->{stages}->[1]}) + scalar(@{$self->{run}->{schedule}->{perfdata}->{stages}->[2]});

    $self->{logger}->writeLogDebug("[mbi-etl] perfdata substeps " . $self->{run}->{schedule}->{perfdata}->{substeps_total});

    $self->watch_etl_perfdata();
}

sub run_etl {
    my ($self, %options) = @_;

    if ($self->{run}->{schedule}->{import}->{status} == PLANNED) {
        $self->run_etl_import();
        return ;
    } elsif ($self->{run}->{schedule}->{dimensions}->{status} == PLANNED) {
        $self->run_etl_dimensions();
        return ;
    }
    if ($self->{run}->{schedule}->{event}->{status} == PLANNED) {
        $self->run_etl_event();
    }
    if ($self->{run}->{schedule}->{perfdata}->{status} == PLANNED) {
        $self->run_etl_perfdata();
    }
}

sub check_stopped_ko_import {
    my ($self, %options) = @_;

    return 0 if ($self->{run}->{schedule}->{import}->{substeps_executed} >= $self->{run}->{schedule}->{import}->{substeps_execute});

    return 1;
}

sub check_stopped_ko_dimensions {
    my ($self, %options) = @_;

    return 0 if ($self->{run}->{schedule}->{dimensions}->{substeps_executed} >= $self->{run}->{schedule}->{dimensions}->{substeps_execute});

    return 1;
}

sub check_stopped_ko_event {
    my ($self, %options) = @_;

    return 0 if ($self->{run}->{schedule}->{event}->{substeps_executed} >= $self->{run}->{schedule}->{event}->{substeps_execute});

    return 1;
}

sub check_stopped_ko_perfdata {
    my ($self, %options) = @_;

    return 0 if ($self->{run}->{schedule}->{perfdata}->{substeps_executed} >= $self->{run}->{schedule}->{perfdata}->{substeps_execute});

    return 1;
}

sub check_stopped_ko {
    my ($self, %options) = @_;

    # if nothing planned. we stop
    if ($self->{run}->{schedule}->{planned} == NOTDONE) {
        $self->reset();
        return 0;
    }

    return 1 if ($self->{run}->{status} != STOP);

    my $stopped = 0;
    $stopped += $self->check_stopped_ko_import()
        if ($self->{run}->{schedule}->{import}->{status} == RUNNING);
    $stopped += $self->check_stopped_ko_dimensions()
        if ($self->{run}->{schedule}->{dimensions}->{status} == RUNNING);
    $stopped += $self->check_stopped_ko_event()
        if ($self->{run}->{schedule}->{event}->{status} == RUNNING);
    $stopped += $self->check_stopped_ko_perfdata()
        if ($self->{run}->{schedule}->{perfdata}->{status} == RUNNING);

    if ($stopped == 0) {
        $self->reset();
        return 0;
    }

    return 1;
}

sub check_stopped_ok_import {
    my ($self, %options) = @_;

    return 0 if ($self->{run}->{schedule}->{import}->{substeps_executed} >= $self->{run}->{schedule}->{import}->{substeps_total});

    return 1;
}

sub check_stopped_ok_dimensions {
    my ($self, %options) = @_;

    return 0 if ($self->{run}->{schedule}->{dimensions}->{substeps_executed} >= $self->{run}->{schedule}->{dimensions}->{substeps_total});

    return 1;
}

sub check_stopped_ok_event {
    my ($self, %options) = @_;

    return 0 if ($self->{run}->{schedule}->{event}->{substeps_executed} >= $self->{run}->{schedule}->{event}->{substeps_total});

    return 1;
}

sub check_stopped_ok_perfdata {
    my ($self, %options) = @_;

    return 0 if ($self->{run}->{schedule}->{perfdata}->{substeps_executed} >= $self->{run}->{schedule}->{perfdata}->{substeps_total});

    return 1;
}

sub check_stopped_ok {
    my ($self, %options) = @_;

    return 1 if ($self->{run}->{status} == STOP);

    my $stopped = 0;
    $stopped += $self->check_stopped_ok_import()
        if ($self->{run}->{schedule}->{import}->{status} == RUNNING);
    $stopped += $self->check_stopped_ok_dimensions()
        if ($self->{run}->{schedule}->{dimensions}->{status} == RUNNING);
    $stopped += $self->check_stopped_ok_event()
        if ($self->{run}->{schedule}->{event}->{status} == RUNNING);
    $stopped += $self->check_stopped_ok_perfdata()
        if ($self->{run}->{schedule}->{perfdata}->{status} == RUNNING);

    if ($stopped == 0) {
        $self->send_log(
            code => GORGONE_ACTION_FINISH_OK,
            token => $self->{run}->{token},
            data => {
                messages => [ ['I', '[SCHEDULER] <<<<<<< end' ] ]
            }
        );
        $self->reset();
        return 0;
    }

    return 1;
}

sub planning {
    my ($self, %options) = @_;

    if ($self->{run}->{options}->{import} == 1) {
        $self->{run}->{schedule}->{import}->{status} = PLANNED;
        $self->{run}->{schedule}->{steps_total}++;
    }
    if ($self->{run}->{options}->{dimensions} == 1) {
        $self->{run}->{schedule}->{dimensions}->{status} = PLANNED;
        $self->{run}->{schedule}->{steps_total}++;
    }
    if ($self->{run}->{options}->{event} == 1) {
        $self->{run}->{schedule}->{event}->{status} = PLANNED;
        $self->{run}->{schedule}->{steps_total}++;
    }
    if ($self->{run}->{options}->{perfdata} == 1) {
        $self->{run}->{schedule}->{perfdata}->{status} = PLANNED;
        $self->{run}->{schedule}->{steps_total}++;
    }

    if ($self->{run}->{schedule}->{steps_total} == 0) {
        die "[SCHEDULING] nothing planned";
    }

    $self->{run}->{schedule}->{steps_executed} = 0;
    $self->{run}->{schedule}->{planned} = DONE;
}

sub check_basic_options {
    my ($self, %options) = @_;

    if (($options{daily} == 0 && $options{rebuild} == 0 && $options{create_tables} == 0 && !defined($options{centile}))
        || ($options{daily} == 1 && $options{rebuild} == 1)) {
        die "Specify one execution method";
    }
    if (($options{rebuild} == 1 || $options{create_tables} == 1) 
        && (($options{start} ne '' && $options{end} eq '') 
        || ($options{start} eq '' && $options{end} ne ''))) {
        die "Specify both options start and end or neither of them to use default data retention options";
    }
    if ($options{rebuild} == 1 && $options{start} ne '' && $options{end} ne ''
        && ($options{start} !~ /[1-2][0-9]{3}\-[0-1][0-9]\-[0-3][0-9]/ || $options{end} !~ /[1-2][0-9]{3}\-[0-1][0-9]\-[0-3][0-9]/)) {
        die "Verify period start or end date format";
    }
}

sub action_centreonmbietlrun {
    my ($self, %options) = @_;

    try {
        $options{token} = $self->generate_token() if (!defined($options{token}));

        return $self->runko(token => $options{token}, msg => '[SCHEDULER] already running') if ($self->{run}->{status} == RUNNING);
        return $self->runko(token => $options{token}, msg => '[SCHEDULER] currently wait previous execution finished - can restart gorgone mbi process') if ($self->{run}->{status} == STOP);

        $self->{run}->{token} = $options{token};
        $self->{run}->{messages} = gorgone::modules::centreon::mbi::libs::Messages->new();

        $self->check_basic_options(%{$options{data}->{content}});

        $self->{run}->{schedule} = {
            steps_total => 0,
            steps_executed => 0,
            planned => NOTDONE,
            import => { status => UNPLANNED, actions => [] },
            dimensions => { status => UNPLANNED },
            event => { status => UNPLANNED, stages => [ [], [], [] ] },
            perfdata => { status => UNPLANNED, stages => [ [], [], [] ] }
        };
        $self->{run}->{status} = RUNNING;
    
        $self->{run}->{options} = $options{data}->{content};

        $self->send_log(code => GORGONE_MODULE_CENTREON_MBIETL_PROGRESS, token => $self->{run}->{token}, data => { messages => [ ['I', '[SCHEDULER] >>>>>>> start' ] ] });

        $self->{run}->{dbmon} = $self->db_parse_xml(file => $self->{cbis_profile}); 
        $self->{run}->{dbbi} = $self->db_parse_xml(file => $self->{reports_profile}); 

        $self->{run}->{dbmon_centreon_con} = gorgone::class::db->new(
            type => 'mysql',
            force => 2,
            logger => $self->{logger},
            die => 1,
            %{$self->{run}->{dbmon}->{centreon}}
        );
        $self->{run}->{dbmon_centstorage_con} = gorgone::class::db->new(
            type => 'mysql',
            force => 2,
            logger => $self->{logger},
            die => 1,
            %{$self->{run}->{dbmon}->{centstorage}}
        );
        $self->{run}->{dbbi_centstorage_con} = gorgone::class::db->new(
            type => 'mysql',
            force => 2,
            logger => $self->{logger},
            die => 1,
            %{$self->{run}->{dbbi}->{centstorage}}
        );

        $self->{etlProp} = gorgone::modules::centreon::mbi::libs::centreon::ETLProperties->new($self->{logger}, $self->{run}->{dbmon_centreon_con});
        ($self->{run}->{etlProperties}, $self->{run}->{dataRetention}) = $self->{etlProp}->getProperties();
    
        $self->planning();
        $self->run_etl();
    } catch {
        $self->runko(msg => $_);
        $self->reset();
    };

    return 0;
}

sub action_centreonmbietllistener {
    my ($self, %options) = @_;

    return 0 if (!defined($options{token}) || $options{token} !~ /^$self->{module_id}-$self->{run}->{token}-(.*?)-(.*)$/);
    my ($type, $indexes) = ($1, $2);

    if ($options{data}->{code} == GORGONE_ACTION_FINISH_KO) {
        $self->{run}->{status} = STOP;
        $self->send_log(code => GORGONE_ACTION_FINISH_KO, token => $self->{run}->{token}, data => $options{data}->{data});
    } elsif ($options{data}->{code} == GORGONE_ACTION_FINISH_OK) {
        $self->send_log(code => GORGONE_MODULE_CENTREON_MBIETL_PROGRESS, token => $self->{run}->{token}, data => $options{data}->{data});
    } else {
        return 0;
    }

    if ($type eq 'import') {
        $self->watch_etl_import(indexes => $indexes);
    } elsif ($type eq 'dimensions') {
        $self->watch_etl_dimensions(indexes => $indexes);
    } elsif ($type eq 'event') {
        $self->watch_etl_event(indexes => $indexes);
    } elsif ($type eq 'perfdata') {
        $self->watch_etl_perfdata(indexes => $indexes);
    }

    return 1;
}

sub action_centreonmbietlkill {
    my ($self, %options) = @_;

    $options{token} = $self->generate_token() if (!defined($options{token}));

    if ($self->{run}->{status} == NONE) {
        $self->{logger}->writeLogDebug('[mbi-etl] kill action - etl not running');
        $self->send_log(
            code => GORGONE_ACTION_FINISH_OK,
            token => $options{token},
            data => {
                messages => 'etl not running'
            }
        );
        return 0;
    } 

    $self->{logger}->writeLogDebug('[mbi-etl] kill sent to the module etlworkers');

    $self->send_internal_action({
        action => 'KILL',
        token => $options{token},
        data => {
            content => {
                package => 'gorgone::modules::centreon::mbi::etlworkers::hooks'
            }
        }
    });

    # RUNNING or STOP
    $self->send_log(
        code => GORGONE_ACTION_CONTINUE,
        token => $options{token},
        data => {
            messages => 'kill sent to the module etlworkers'
        }
    );

    $self->reset();

    return 0;
}

sub action_centreonmbietlstatus {
    my ($self, %options) = @_;

    $options{token} = $self->generate_token() if (!defined($options{token}));

    my $map_etl_status = {
        0 => 'ready',
        1 => 'running',
        2 => 'stopping'
    };

    my $map_planning_status = {
        0 => 'running',
        1 => 'ok'
    };

    my $map_section_status = {
        -1 => 'unplanned',
        0 => 'planned',
        1 => 'running',
        2 => 'ok'
    };

    my $section = {};
    foreach ('import', 'dimensions', 'event', 'perfdata') {
        next if (!defined($self->{run}->{schedule}));

        $section->{$_} = {
            status => $self->{run}->{schedule}->{$_}->{status},
            statusStr => $map_section_status->{ $self->{run}->{schedule}->{$_}->{status} }
        };
        if ($self->{run}->{schedule}->{$_}->{status} == RUNNING) {
            $section->{$_}->{steps_total} = $self->{run}->{schedule}->{$_}->{substeps_total};
            $section->{$_}->{steps_executed} = $self->{run}->{schedule}->{$_}->{substeps_executed};
        }
    }

    $self->send_log(
        code => GORGONE_ACTION_FINISH_OK,
        token => $options{token},
        data => {
            token => defined($self->{run}->{token}) ? $self->{run}->{token} : undef,

            status => $self->{run}->{status},
            statusStr => $map_etl_status->{ $self->{run}->{status} },

            planning => defined($self->{run}->{schedule}->{planned}) ? $self->{run}->{schedule}->{planned} : undef,
            planningStr => defined($self->{run}->{schedule}->{planned}) ? $map_planning_status->{ $self->{run}->{schedule}->{planned} } : undef,

            sections => $section
        }
    );

    return 0;
}



sub periodic_exec {
    if ($connector->{stop} == 1) {
        $connector->{logger}->writeLogInfo("[" . $connector->{module_id} . "] $$ has quit");
        exit(0);
    }
}

sub run {
    my ($self, %options) = @_;

    $self->{internal_socket} = gorgone::standard::library::connect_com(
        context => $self->{zmq_context},
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgone-' . $self->{module_id},
        logger => $self->{logger},
        type => $self->get_core_config(name => 'internal_com_type'),
        path => $self->get_core_config(name => 'internal_com_path')
    );
    $self->send_internal_action({
        action => 'CENTREONMBIETLREADY',
        data => {}
    });

    my $w1 = EV::timer(5, 2, \&periodic_exec);
    my $w2 = EV::io($self->{internal_socket}->get_fd(), EV::READ, sub { $connector->event() } );
    EV::run();
}

1;
