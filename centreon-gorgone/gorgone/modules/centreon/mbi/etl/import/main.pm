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

package gorgone::modules::centreon::mbi::etl::import::main;

use strict;
use warnings;

use gorgone::modules::centreon::mbi::libs::bi::MySQLTables;
use gorgone::modules::centreon::mbi::libs::Utils;

my ($biTables, $monTables, $utils);
my ($argsMon, $argsBi);

sub initVars {
    my ($etl) = @_;

    $biTables = gorgone::modules::centreon::mbi::libs::bi::MySQLTables->new($etl->{run}->{messages}, $etl->{run}->{dbbi_centstorage_con});
    $monTables = gorgone::modules::centreon::mbi::libs::bi::MySQLTables->new($etl->{run}->{messages}, $etl->{run}->{dbmon_centstorage_con});
    $utils = gorgone::modules::centreon::mbi::libs::Utils->new($etl->{run}->{messages});
    $argsMon = $utils->buildCliMysqlArgs($etl->{run}->{dbmon}->{centstorage});
    $argsBi = $utils->buildCliMysqlArgs($etl->{run}->{dbbi}->{centstorage});
}

# Create tables for centstorage database on reporting server
sub createTables {
    my ($etl, $periods, $options, $notTimedTables) = @_;

    #Creating all centreon bi tables exept the one already created
    my $sth = $etl->{run}->{dbmon_centstorage_con}->query("SHOW TABLES LIKE 'mod_bi_%'");
    while (my @row = $sth->fetchrow_array()) {
        my $name = $row[0];
        if (!$biTables->tableExists($name)) { 
            my $structure = $monTables->dumpTableStructure($name);
            push @{$etl->{run}->{schedule}->{import}->{actions}},
                {
                    type => 1, db => 'centstorage', sql => [ ["[CREATE] add table [$name]", $structure] ], actions => []
                };
        }
    }

    # Manage centreonAcl
    my $action;
    if ($options->{create_tables} == 0) {
        #Update centreon_acl table each time centreon-only is started - not the best way but need for Widgets
        my $cmd = sprintf(
            "mysqldump --replace --no-create-info --skip-add-drop-table --skip-add-locks --skip-comments %s '%s' %s | mysql %s '%s'",
            $argsMon,
            $etl->{run}->{dbmon}->{centstorage}->{db},
            'centreon_acl',
            $argsBi,
            $etl->{run}->{dbbi}->{centstorage}->{db}
        );
        $action = { type => 2, message => '[LOAD] import table [centreon_acl]', command => $cmd };
    }    

    if (!$biTables->tableExists('centreon_acl')) {
        my $structure = $monTables->dumpTableStructure('centreon_acl');
        push @{$etl->{run}->{schedule}->{import}->{actions}},
            {
                type => 1, db => 'centstorage', sql => [ ["[CREATE] add table [centreon_acl]", $structure] ], actions => defined($action) ? [$action] : []
            };
    } elsif (defined($action)) {
        push @{$etl->{run}->{schedule}->{import}->{actions}}, $action;
    }

    my $tables = join('|', @$notTimedTables);
    $sth = $etl->{run}->{dbmon_centstorage_con}->query("SHOW TABLES LIKE 'mod_bam_reporting_%'");
    while (my @row = $sth->fetchrow_array()) {
        my $name = $row[0];
        next if ($name =~ /^(?:$tables)$/);

        if (!$biTables->tableExists($name)) { 
            my $structure = $monTables->dumpTableStructure($name);
            push @{$etl->{run}->{schedule}->{import}->{actions}},
                {
                    type => 1, db => 'centstorage', sql => [ ["[CREATE] Add table [$name]", $structure] ], actions => []
                };
        }
    }
}

# Extract data from Centreon DB server
sub extractData {
    my ($etl, $options, $notTimedTables) = @_;

    foreach my $name (@$notTimedTables) {
        my $action = { type => 1, db => 'centstorage', sql => [], actions => [] };

        push @{$action->{sql}}, [ '[CREATE] Deleting table [' . $name . ']', 'DROP TABLE IF EXISTS `' . $name . '`' ];

        my $structure = $monTables->dumpTableStructure($name);
        $structure =~ s/(CONSTRAINT.*\n)//g;
        $structure =~ s/(\,\n\s+\))/\)/g;
        $structure =~ s/auto_increment\=[0-9]+//i;
        $structure =~ s/auto_increment//i;

        push @{$action->{sql}}, [ "[CREATE] Add table [$name]", $structure ];
        if ($name eq 'hoststateevents' || $name eq 'servicestateevents') {
            # add drop indexes
            my $indexes = $etl->{run}->{dbmon_centstorage_con}->query("SHOW INDEX FROM " . $name);
            my $previous = '';
            while (my $row = $indexes->fetchrow_hashref()) {
                if ($row->{Key_name} ne $previous) {
                    if (lc($row->{Key_name}) eq lc('PRIMARY')) {
                        push @{$action->{sql}}, 
                        [
                            "[INDEXING] Deleting index [PRIMARY KEY] on table [".$name."]",
                            "ALTER TABLE `" . $name . "` DROP PRIMARY KEY"
                        ];
                    } else {
                        push @{$action->{sql}}, 
                        [
                            "[INDEXING] Deleting index [$row->{Key_name}] on table [".$name."]",
                            "ALTER TABLE `" . $name . "` DROP INDEX " . $row->{Key_name}
                        ];
                    }
                }
                $previous = $row->{Key_name};
            }

            push @{$action->{sql}}, 
                [
                    "[INDEXING] Adding index [in_downtime, start_time, end_time] on table [" . $name . "]",
                    "ALTER TABLE `" . $name . "` ADD INDEX `idx_" . $name . "_downtime_start_end_time` (in_downtime, start_time, end_time)"
                ],
                [
                    "[INDEXING] Adding index [end_time] on table [" . $name . "]",
                    "ALTER TABLE `" . $name . "` ADD INDEX `idx_" . $name . "_end_time` (`end_time`)"
                ];
            if ($name eq 'servicestateevents') {
                push @{$action->{sql}}, 
                [
                    "[INDEXING] Adding index [host_id, service_id, start_time, end_time, ack_time, state, last_update] on table [servicestateevents]",
                    "ALTER TABLE `servicestateevents` ADD INDEX `idx_servicestateevents_multi` (host_id, service_id, start_time, end_time, ack_time, state, last_update)"
                ];
            }
        }

        my $cmd = sprintf(
            "mysqldump --no-create-info --skip-add-drop-table --skip-add-locks --skip-comments %s '%s' %s | mysql %s '%s'",
            $argsMon,
            $etl->{run}->{dbmon}->{centstorage}->{db},
            $name,
            $argsBi,
            $etl->{run}->{dbbi}->{centstorage}->{db}
        );
        push @{$action->{actions}}, { type => 2, message => '[LOAD] import table [' . $name . ']', command => $cmd };
        push @{$etl->{run}->{schedule}->{import}->{actions}}, $action;
    }
}

# load data into the reporting server from files copied from the monitoring server
sub extractCentreonDB {
	my ($etl, $etlProperties) = @_;

    my $tables = 'host hostgroup_relation hostgroup hostcategories_relation hostcategories ' .
        'host_service_relation service service_categories service_categories_relation ' .
        'timeperiod mod_bi_options servicegroup mod_bi_options_centiles servicegroup_relation contact contactgroup_service_relation '.
        'host_template_relation command contact_host_relation contactgroup_host_relation contactgroup contact_service_relation';

    my $mon = $utils->buildCliMysqlArgs($etl->{run}->{dbmon}->{centreon});
    my $bi = $utils->buildCliMysqlArgs($etl->{run}->{dbbi}->{centreon});

    my $cmd = sprintf(
        "mysqldump --replace --skip-add-drop-table --skip-add-locks --skip-comments %s '%s' %s | mysql --force %s '%s'",
        $mon,
        $etl->{run}->{dbmon}->{centreon}->{db},
        $tables,
        $bi,
        $etl->{run}->{dbbi}->{centreon}->{db}
    );

    push @{$etl->{run}->{schedule}->{import}->{actions}}, 
        { type => 2, message => '[LOAD] import table [' . $tables . ']', command => $cmd };
}

sub dataBin {
    my ($etl, $etlProperties, $options, $periods) = @_;

    return if ($options->{ignore_databin} == 1 || $options->{centreon_only} == 1 || (defined($options->{bam_only}) && $options->{bam_only} == 1));

    my $action = { type => 1, db => 'centstorage', sql => [], actions => [] };

    my $drop = 0;
    if ($options->{rebuild} == 1 && $options->{nopurge} == 0) {
        push @{$action->{sql}}, [ '[CREATE] Deleting table [data_bin]', 'DROP TABLE IF EXISTS `data_bin`' ];
        $drop = 1;
    }

    my $isExists = 0;
    $isExists = 1 if ($biTables->tableExists('data_bin'));

    my $partitionsPerf = $utils->getRangePartitionDate($periods->{raw_perfdata}->{start}, $periods->{raw_perfdata}->{end});

    if ($isExists == 0 || $drop == 1) {
        $action->{create} = 1;

        my $structure = $monTables->dumpTableStructure('data_bin');
        $structure =~ s/KEY.*\(\`id_metric\`\)\,//g;
        $structure =~ s/KEY.*\(\`id_metric\`\)//g;
        $structure =~ s/\n.*PARTITION.*//g;
        $structure =~ s/\,[\n\s]+\)/\)/;
        $structure .= " PARTITION BY RANGE(`ctime`) (";

        my $append = '';
        foreach (@$partitionsPerf) {
            $structure .= $append . "PARTITION p" . $_->{name} . " VALUES LESS THAN (" . $_->{epoch} . ")";
            $append = ',';
        }
        $structure .= ');';

        push @{$action->{sql}},
            [ '[CREATE] Add table [data_bin]', $structure ],
            [ '[INDEXING] Adding index [ctime] on table [data_bin]', "ALTER TABLE `data_bin` ADD INDEX `idx_data_bin_ctime` (`ctime`)" ],
            [ '[INDEXING] Adding index [id_metric_id, ctime] on table [data_bin]', "ALTER TABLE `data_bin` ADD INDEX `idx_data_bin_idmetric_ctime` (`id_metric`,`ctime`)" ];
    }

    if ($isExists == 1 && $drop == 0) {
        my $start = $biTables->getLastPartRange('data_bin');
        my $partitions = $utils->getRangePartitionDate($start, $periods->{raw_perfdata}->{end});
        foreach (@$partitions) {
            push @{$action->{sql}}, 
                [ '[PARTITIONS] Add partition [' . $_->{name} . '] on table [data_bin]', "ALTER TABLE `data_bin` ADD PARTITION (PARTITION `p$_->{name}` VALUES LESS THAN($_->{epoch}))"];
        }
    }

    if ($etl->{run}->{options}->{create_tables} == 0 && ($etlProperties->{'statistics.type'} eq 'all' || $etlProperties->{'statistics.type'} eq 'perfdata')) {
        my $epoch = $utils->getDateEpoch($periods->{raw_perfdata}->{start});

        my $overCond = 'ctime >= ' . $epoch .  ' AND ';
        foreach (@$partitionsPerf) {
            my $cmd = sprintf(
                "mysqldump --insert-ignore --single-transaction --no-create-info --skip-add-drop-table --skip-disable-keys --skip-add-locks --skip-comments %s --databases '%s' --tables %s --where=\"%s\" | mysql --init-command='SET SESSION unique_checks=0' %s '%s'",
                $argsMon,
                $etl->{run}->{dbmon}->{centstorage}->{db},
                'data_bin',
                $overCond . 'ctime < ' . $_->{epoch},
                $argsBi,
                $etl->{run}->{dbbi}->{centstorage}->{db}
            );
            $overCond = 'ctime >= ' . $_->{epoch} . ' AND ';
            push @{$action->{actions}}, { type => 2, message => '[LOAD] partition [' . $_->{name} . '] on table [data_bin]', command => $cmd };
        }

        #my $file = $etlProperties->{'reporting.storage.directory'} . '/data_bin.sql';
        #push @{$action->{actions}}, {
        #    type => 3,
        #    message => '[LOAD] table [data_bin]',
        #    table => 'data_bin',
        #    db => 'centstorage',
        #    dump => $cmd,
        #    file => $file,
        #    load => "LOAD DATA LOCAL INFILE '" . $file . "' INTO TABLE `data_bin` CHARACTER SET UTF8 IGNORE 1 LINES"
        #};
    }

    push @{$etl->{run}->{schedule}->{import}->{actions}}, $action;
}

sub selectTables {
    my ($etl, $etlProperties, $options) = @_;

    my @notTimedTables = ();
    my %timedTables = ();

    my @ctime = ('ctime', 'ctime');
    my @startEnd = ('date_start', 'date_end');
    my @timeId = ('time_id', 'time_id');
    my $importComment = $etlProperties->{'import.comments'};
	my $importDowntimes = $etlProperties->{'import.downtimes'};

    if (!defined($etlProperties->{'statistics.type'})) {
        die 'cannot determine statistics type or compatibility mode for data integration';
    }

    if (!defined($options->{databin_only}) || $options->{databin_only} == 0) {
	  if (!defined($options->{bam_only}) || $options->{bam_only} == 0) {
        if ($etlProperties->{'statistics.type'} eq 'all') {
            push @notTimedTables, 'index_data';
            push @notTimedTables, 'metrics';
            push @notTimedTables, 'hoststateevents';
            push @notTimedTables, 'servicestateevents';
            push @notTimedTables, 'instances';
            push @notTimedTables, 'hosts';

            if ($importComment eq 'true'){
                push @notTimedTables, 'comments';
            }
            if ($importDowntimes eq 'true'){
                push @notTimedTables, 'downtimes';
            }

            push @notTimedTables, 'acknowledgements';
        }
        if ($etlProperties->{'statistics.type'} eq 'availability') {
            push @notTimedTables, 'hoststateevents';
            push @notTimedTables, 'servicestateevents';
            push @notTimedTables, 'instances';
            push @notTimedTables, 'hosts';
            if ($importComment eq 'true'){
                push @notTimedTables, 'comments';
            }
            push @notTimedTables, 'acknowledgements';
        }
        if ($etlProperties->{'statistics.type'} eq "perfdata") {
            push @notTimedTables, 'index_data';
            push @notTimedTables, 'metrics';
            push @notTimedTables, 'instances';
            push @notTimedTables, 'hosts';
            push @notTimedTables, 'acknowledgements';

        }
    }

	my $sth = $etl->{run}->{dbmon_centreon_con}->query("SELECT id FROM modules_informations WHERE name='centreon-bam-server'");
	if (my $row = $sth->fetchrow_array() && $etlProperties->{'statistics.type'} ne 'perfdata') {
            push @notTimedTables, "mod_bam_reporting_ba_availabilities";
			push @notTimedTables, "mod_bam_reporting_ba";
            push @notTimedTables, "mod_bam_reporting_ba_events";
            push @notTimedTables, "mod_bam_reporting_ba_events_durations";
            push @notTimedTables, "mod_bam_reporting_bv";
            push @notTimedTables, "mod_bam_reporting_kpi";
            push @notTimedTables, "mod_bam_reporting_kpi_events";
            push @notTimedTables, "mod_bam_reporting_relations_ba_bv";
            push @notTimedTables, "mod_bam_reporting_relations_ba_kpi_events";
			push @notTimedTables, "mod_bam_reporting_timeperiods";
        }
    }

    return (\@notTimedTables, \%timedTables);
}

sub prepare {
    my ($etl) = @_;

    initVars($etl);

    # define data extraction period based on program options --start & --end or on data retention period
    my %periods;
    if ($etl->{run}->{options}->{rebuild} == 1 || $etl->{run}->{options}->{create_tables}) {
        if ($etl->{run}->{options}->{start} eq '' && $etl->{run}->{options}->{end} eq '') {
            # get max values for retention by type of statistics in order to be able to rebuild hourly and daily stats
            my ($start, $end) = $etl->{etlProp}->getMaxRetentionPeriodFor('perfdata');

            $periods{raw_perfdata} = { start => $start, end => $end };
            ($start, $end) = $etl->{etlProp}->getMaxRetentionPeriodFor('availability');
            $periods{raw_availabilitydata} = { start => $start, end => $end};
        } elsif ($etl->{run}->{options}->{start} ne '' && $etl->{run}->{options}->{end} ne '') {
            # set period defined manually
            my %dates = (start => $etl->{run}->{options}->{start}, end => $etl->{run}->{options}->{end});
            $periods{raw_perfdata} = \%dates;
            $periods{raw_availabilitydata} = \%dates;
        }
    } else {
        # set yesterday start and end dates as period (--daily)
        my %dates;
        ($dates{start}, $dates{end}) = $utils->getYesterdayTodayDate();
        $periods{raw_perfdata} = \%dates;
        $periods{raw_availabilitydata} = \%dates;
    }

    # identify the Centreon Storage DB tables to extract based on ETL properties
    my ($notTimedTables, $timedTables) = selectTables(
        $etl,
        $etl->{run}->{etlProperties},
        $etl->{run}->{options}
    );

    dataBin(
        $etl,
        $etl->{run}->{etlProperties},
        $etl->{run}->{options},
        \%periods
    );

    # create non existing tables
    createTables($etl, \%periods, $etl->{run}->{options}, $notTimedTables);

    # If we only need to create empty tables, create them then exit program
    return if ($etl->{run}->{options}->{create_tables} == 1);

    # extract raw availability and perfdata from monitoring server and insert it into reporting server
    if ($etl->{run}->{options}->{centreon_only} == 0) {
        extractData($etl, $etl->{run}->{options}, $notTimedTables);
    }

    # extract Centreon configuration DB from monitoring server and insert it into reporting server
    if ((!defined($etl->{run}->{options}->{databin_only}) || $etl->{run}->{options}->{databin_only} == 0) 
        && (!defined($etl->{run}->{options}->{bam_only}) || $etl->{run}->{options}->{bam_only} == 0)) {
        extractCentreonDB($etl, $etl->{run}->{etlProperties});
    }
}

1;
