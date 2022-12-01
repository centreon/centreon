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

package gorgone::modules::centreon::mbi::etl::event::main;

use strict;
use warnings;

use gorgone::modules::centreon::mbi::libs::bi::Time;
use gorgone::modules::centreon::mbi::libs::bi::LiveService;
use gorgone::modules::centreon::mbi::libs::bi::MySQLTables;
use gorgone::modules::centreon::mbi::libs::Utils;

my ($biTables, $utils, $liveService, $time);
my ($start, $end);

sub initVars {
    my ($etl) = @_;

    $biTables = gorgone::modules::centreon::mbi::libs::bi::MySQLTables->new($etl->{run}->{messages}, $etl->{run}->{dbbi_centstorage_con});
    $utils = gorgone::modules::centreon::mbi::libs::Utils->new($etl->{run}->{messages});
    $liveService = gorgone::modules::centreon::mbi::libs::bi::LiveService->new($etl->{run}->{messages}, $etl->{run}->{dbbi_centstorage_con});
    $time = gorgone::modules::centreon::mbi::libs::bi::Time->new($etl->{run}->{messages}, $etl->{run}->{dbbi_centstorage_con});
}

sub emptyTableForRebuild {
    my ($etl, %options) = @_;

    my $sql = [ [ '[CREATE] Deleting table [' . $options{name} . ']', 'DROP TABLE IF EXISTS `' . $options{name} . '`' ] ];

    my $structure = $biTables->dumpTableStructure($options{name});
    $structure =~ s/KEY.*\(\`$options{column}\`\)\,//g;
	$structure =~ s/KEY.*\(\`$options{column}\`\)//g;
	$structure =~ s/\,[\n\s+]+\)/\n\)/g;
    
    if (defined($options{start})) {
        $structure =~ s/\n.*PARTITION.*//g;
        $structure =~ s/\,[\n\s]+\)/\)/;
        $structure .= ' PARTITION BY RANGE(`' . $options{column} . '`) (';

        my $partitionsPerf = $utils->getRangePartitionDate($options{start}, $options{end});

        my $append = '';
        foreach (@$partitionsPerf) {
            $structure .= $append . "PARTITION p" . $_->{name} . " VALUES LESS THAN (" . $_->{epoch} . ")";
            $append = ',';
        }
        $structure .= ');';
    }

    push @$sql,
        [ '[CREATE] Add table [' . $options{name} . ']', $structure ],
        [ "[INDEXING] Adding index [idx_$options{name}_$options{column}] on table [$options{name}]", "ALTER TABLE `$options{name}` ADD INDEX `idx_$options{name}_$options{column}` (`$options{column}`)" ];

    push @{$etl->{run}->{schedule}->{event}->{stages}->[0]}, { type => 'sql', db => 'centstorage', sql => $sql };
}

sub deleteEntriesForRebuild {
    my ($etl, %options) = @_;

    my $sql = [];
    if (!$biTables->isTablePartitioned($options{name})) {
        push @$sql,
            [
                "[PURGE] Delete table [$options{name}] from $options{start} to $options{end}",
                "DELETE FROM $options{name} WHERE time_id >= " . $utils->getDateEpoch($options{start}) . " AND time_id < " . $utils->getDateEpoch($options{end})
            ];
	} else {
        my $partitionsPerf = $utils->getRangePartitionDate($options{start}, $options{end});
        foreach (@$partitionsPerf) {
            push @$sql,
                [
                    "[PURGE] Truncate partition $_->{name} on table [$options{name}]",
                    "ALTER TABLE $options{name} TRUNCATE PARTITION p$_->{name}"
                ];
        }
	}

    push @{$etl->{run}->{schedule}->{event}->{stages}->[0]}, { type => 'sql', db => 'centstorage', sql => $sql };
}

sub purgeAvailabilityTables {
	my ($etl, $start, $end) = @_;

	my $firstDayOfMonth = $start;
    $firstDayOfMonth =~ s/([1-2][0-9]{3})\-([0-1][0-9])\-[0-3][0-9]/$1\-$2\-01/;

	if ($etl->{run}->{options}->{nopurge} == 0) {
		if (!defined($etl->{run}->{options}->{service_only}) || $etl->{run}->{options}->{service_only} == 0) {
			if (!defined($etl->{run}->{options}->{month_only}) || $etl->{run}->{options}->{month_only} == 0) {
    			emptyTableForRebuild($etl, name => 'mod_bi_hostavailability', column => 'time_id', start => $start, end => $end);
			}

            emptyTableForRebuild($etl, name => 'mod_bi_hgmonthavailability', column => 'time_id');
		}
		if (!defined($etl->{run}->{options}->{host_only}) || $etl->{run}->{options}->{host_only} == 0) {
			if (!defined($etl->{run}->{options}->{month_only}) || $etl->{run}->{options}->{month_only} == 0) {
                emptyTableForRebuild($etl, name => 'mod_bi_serviceavailability', column => 'time_id', start => $start, end => $end);
			}

            emptyTableForRebuild($etl, name => 'mod_bi_hgservicemonthavailability', column => 'time_id');
		}
	} else {
        if (!defined($etl->{run}->{options}->{service_only}) || $etl->{run}->{options}->{service_only} == 0) {
            if (!defined($etl->{run}->{options}->{month_only}) || $etl->{run}->{options}->{month_only} == 0) {
                deleteEntriesForRebuild($etl, name => 'mod_bi_hostavailability', start => $start, end => $end);
            }

            deleteEntriesForRebuild($etl, name => 'mod_bi_hgmonthavailability', start => $firstDayOfMonth, end => $end);
        }
        if (!defined($etl->{run}->{options}->{host_only}) || $etl->{run}->{options}->{host_only} == 0) {
            if (!defined($etl->{run}->{options}->{month_only}) || $etl->{run}->{options}->{month_only} == 0) {
                deleteEntriesForRebuild($etl, name => 'mod_bi_serviceavailability', start => $start, end => $end);
            }
            deleteEntriesForRebuild($etl, name => 'mod_bi_hgservicemonthavailability', start => $firstDayOfMonth, end => $end);
        }
    }    
}

sub processByDay {
	my ($etl, $liveServices, $start, $end) = @_;
			
   	while (my ($liveserviceName, $liveserviceId) = each (%$liveServices)) {
   		if (!defined($etl->{run}->{options}->{service_only}) || $etl->{run}->{options}->{service_only} == 0) {
            push @{$etl->{run}->{schedule}->{event}->{stages}->[1]}, {
                type => 'availability_day_hosts',
                liveserviceName => $liveserviceName,
                liveserviceId => $liveserviceId,
                start => $start,
                end => $end
            };
        }
		
        if (!defined($etl->{run}->{options}->{host_only}) || $etl->{run}->{options}->{host_only} == 0) {
            push @{$etl->{run}->{schedule}->{event}->{stages}->[1]}, {
                type => 'availability_day_services',
                liveserviceName => $liveserviceName,
                liveserviceId => $liveserviceId,
                start => $start,
                end => $end
            };
        }
   	}
}

sub processHostgroupAvailability {
	my ($etl, $start, $end) = @_;

	$time->insertTimeEntriesForPeriod($start, $end);
	if (!defined($etl->{run}->{options}->{service_only}) || $etl->{run}->{options}->{service_only} == 0) {
		push @{$etl->{run}->{schedule}->{event}->{stages}->[2]}, {
            type => 'availability_month_services',
            start => $start,
            end => $end
        };
	}
	if (!defined($etl->{run}->{options}->{host_only}) || $etl->{run}->{options}->{host_only} == 0) {
        push @{$etl->{run}->{schedule}->{event}->{stages}->[2]}, {
            type => 'availability_month_hosts',
            start => $start,
            end => $end
        };
	}
}

sub dailyProcessing {
    my ($etl, $liveServices) = @_;

    # getting yesterday start and end date to process yesterday data
    my ($start, $end) = $utils->getYesterdayTodayDate();
    # daily mod_bi_time table filling
    $time->insertTimeEntriesForPeriod($start, $end);

    my ($epoch, $partName) = $utils->getDateEpoch($end);
    push @{$etl->{run}->{schedule}->{event}->{stages}->[0]}, {
        type => 'sql',
        db => 'centstorage',
        sql => [
            [
                '[PARTITIONS] Add partition [p' . $partName . '] on table [mod_bi_hostavailability]',
                "ALTER TABLE `mod_bi_hostavailability` ADD PARTITION (PARTITION `p$partName` VALUES LESS THAN(" . $epoch . "))"
            ]
        ]
    };
    push @{$etl->{run}->{schedule}->{event}->{stages}->[0]}, {
        type => 'sql',
        db => 'centstorage',
        sql => [
            [
                '[PARTITIONS] Add partition [p' . $partName . '] on table [mod_bi_serviceavailability]',
                "ALTER TABLE `mod_bi_serviceavailability` ADD PARTITION (PARTITION `p$partName` VALUES LESS THAN(" . $epoch . "))"
            ]
        ]
    };

    # Calculating availability of hosts and services for the current day
    processByDay($etl, $liveServices, $start, $end);

    # Calculating statistics for last month if day of month si 1
    my ($year, $mon, $day) = split('-', $end);
    if ($day == 1) {
        processHostgroupAvailability($etl, $utils->subtractDateMonths($end, 1), $utils->subtractDateDays($end, 1));
    }

    push @{$etl->{run}->{schedule}->{event}->{stages}->[0]},
        { type => 'events', services => 1, start => $start, end => $end }, { type => 'events', hosts => 1, start => $start, end => $end };
}

# rebuild availability statistics
sub rebuildAvailability {
	my ($etl, $start, $end, $liveServices) = @_;

    my $days = $utils->getRangePartitionDate($start, $end);
    foreach (@$days) {
        $end = $_->{date};
        processByDay($etl, $liveServices, $start, $end);

        my ($year, $mon, $day) = split('-', $end);
        if ($day == 1) {
            processHostgroupAvailability($etl, $utils->subtractDateMonths($end, 1), $utils->subtractDateDays($end, 1));
        }

        $start = $end;
    }
}

sub rebuildProcessing {
    my ($etl, $liveServices) = @_;
    
    if ($etl->{run}->{options}->{start} ne '' && $etl->{run}->{options}->{end} ne '') {
        # setting manually start and end dates for each granularity of perfdata
        ($start, $end) = ($etl->{run}->{options}->{start}, $etl->{run}->{options}->{end});
    }else {
        # getting max perfdata retention period to fill mod_bi_time
        my $periods = $etl->{etlProp}->getRetentionPeriods();
        ($start, $end) = ($periods->{'availability.daily'}->{start}, $periods->{'availability.daily'}->{end});
    }

    # insert entries into table mod_bi_time
    $time->insertTimeEntriesForPeriod($start, $end);
    if (!defined($etl->{run}->{options}->{events_only}) || $etl->{run}->{options}->{events_only} == 0) {
        purgeAvailabilityTables($etl, $start, $end);
        rebuildAvailability($etl, $start, $end, $liveServices);
    }

    if (!defined($etl->{run}->{options}->{availability_only}) || $etl->{run}->{options}->{availability_only} == 0) {
        push @{$etl->{run}->{schedule}->{event}->{stages}->[0]},
            { type => 'events', services => 1, start => $start, end => $end }, { type => 'events', hosts => 1, start => $start, end => $end };
    }
}

sub prepare {
    my ($etl) = @_;

    initVars($etl);

    my $liveServiceList = $liveService->getLiveServicesByNameForTpIds($etl->{run}->{etlProperties}->{'liveservices.availability'});

    if ($etl->{run}->{options}->{daily} == 1) {
        dailyProcessing($etl, $liveServiceList);
    } elsif ($etl->{run}->{options}->{rebuild} == 1) {
        rebuildProcessing($etl, $liveServiceList);
    }
}

1;
