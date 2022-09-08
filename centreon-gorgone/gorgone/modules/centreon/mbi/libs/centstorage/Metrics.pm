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

use strict;
use warnings;

package gorgone::modules::centreon::mbi::libs::centstorage::Metrics;

# Constructor
# parameters:
# $logger: instance of class CentreonLogger
# $centreon: Instance of centreonDB class for connection to Centreon database
# $centstorage: (optionnal) Instance of centreonDB class for connection to Centstorage database
sub new {
    my $class = shift;
    my $self  = {};
    $self->{logger}    = shift;
    $self->{centstorage} = shift;

    $self->{metrics} = ();
    $self->{name} = 'data_bin';
    $self->{timeColumn} = 'ctime';
    $self->{name_minmaxavg_tmp} = 'mod_bi_tmp_minmaxavgvalue';
    $self->{name_firstlast_tmp} = 'mod_bi_tmp_firstlastvalues';
    $self->{name_minmaxctime_tmp} = 'mod_bi_tmp_minmaxctime';
    if (@_) {
        $self->{name_minmaxavg_tmp} .= $_[0];
        $self->{name_firstlast_tmp} .= $_[0];
        $self->{name_minmaxctime_tmp} .= $_[0];
    }
    bless $self, $class;
    return $self;
}

sub getName() {
    my $self = shift;
    return $self->{'name'};
}

sub getTimeColumn() {
    my $self = shift;
    return $self->{'timeColumn'};
}

sub createTempTableMetricMinMaxAvgValues {
    my ($self, $useMemory, $granularity) = @_;
    my $db = $self->{"centstorage"};
    $db->query({ query => "DROP TABLE IF EXISTS `" . $self->{name_minmaxavg_tmp} . "`" });
    my $createTable = " CREATE TABLE `" . $self->{name_minmaxavg_tmp} . "` (";
    $createTable .= " id_metric INT NULL,";
    $createTable .= " avg_value FLOAT NULL,";
    $createTable .= " min_value FLOAT NULL,";
    $createTable .= " max_value FLOAT NULL";
    if ($granularity eq "hour") {
        $createTable .= ", valueTime DATETIME NULL";
    }
    if (defined($useMemory) && $useMemory eq "true") {
        $createTable .= ") ENGINE=MEMORY CHARSET=utf8 COLLATE=utf8_general_ci;";
    }else {
        $createTable .= ") ENGINE=INNODB CHARSET=utf8 COLLATE=utf8_general_ci;";
    }
    $db->query({ query => $createTable });
}

sub getMetricValueByHour {
    my $self = shift;
    my $db = $self->{"centstorage"};
    my $logger = $self->{"logger"};
    
    my ($start, $end, $useMemory) = @_;
    my $dateFormat = "%Y-%c-%e %k:00:00";
    
    # Getting min, max, average
    $self->createTempTableMetricMinMaxAvgValues($useMemory, "hour");
    my $query = "INSERT INTO `" . $self->{name_minmaxavg_tmp} .  "` SELECT id_metric, avg(value) as avg_value, min(value) as min_value, max(value) as max_value, ";
    $query .=     " date_format(FROM_UNIXTIME(ctime), '".$dateFormat."') as valueTime ";
    $query .= "FROM data_bin ";
    $query .= "WHERE ";
    $query .= "ctime >=UNIX_TIMESTAMP('".$start."') AND ctime < UNIX_TIMESTAMP('".$end."') ";
    $query .= "GROUP BY id_metric, date_format(FROM_UNIXTIME(ctime), '".$dateFormat."')";
    
    $db->query({ query => $query });
    $self->addIndexTempTableMetricMinMaxAvgValues("hour");
}

sub getMetricsValueByDay {
    my $self = shift;
    my $db = $self->{"centstorage"};
    my $logger = $self->{"logger"};
    
    my ($period, $useMemory) = @_;
    my $dateFormat = "%Y-%c-%e";
    
    # Getting min, max, average
    $self->createTempTableMetricMinMaxAvgValues($useMemory, "day");
    my $query = "INSERT INTO `" . $self->{name_minmaxavg_tmp} . "` SELECT id_metric, avg(value) as avg_value, min(value) as min_value, max(value) as max_value ";
    #$query .=     " date_format(FROM_UNIXTIME(ctime), '".$dateFormat."') as valueTime ";
    $query .= "FROM data_bin ";
    $query .= "WHERE ";
    my @tabPeriod = @$period;
    my ($start_date, $end_date);
    my $tabSize = scalar(@tabPeriod);
    for (my $count = 0; $count < $tabSize; $count++) {
        my $range = $tabPeriod[$count];
        if ($count == 0) {
            $start_date = $range->[0];
        }
        if ($count == $tabSize - 1) {
            $end_date = $range->[1];
        }
        $query .= "(ctime >= UNIX_TIMESTAMP(".($range->[0]). ") AND ctime < UNIX_TIMESTAMP(".($range->[1]) .")) OR ";
    }
    
    $query =~  s/OR $//;
    $query .= "GROUP BY id_metric";

    $db->query({ query => $query });
    $self->addIndexTempTableMetricMinMaxAvgValues("day");
    $self->getFirstAndLastValues($start_date, $end_date, $useMemory);
}

sub createTempTableMetricDayFirstLastValues {
    my ($self, $useMemory) = @_;
    my $db = $self->{"centstorage"};
    $db->query({ query => "DROP TABLE IF EXISTS `" . $self->{name_firstlast_tmp} . "`" });
    my $createTable = " CREATE TABLE `" . $self->{name_firstlast_tmp} . "` (";
    $createTable .= " `first_value` FLOAT NULL,";
    $createTable .= " `last_value` FLOAT NULL,";
    $createTable .= " id_metric INT NULL";
    if (defined($useMemory) && $useMemory eq "true") {
        $createTable .= ") ENGINE=MEMORY CHARSET=utf8 COLLATE=utf8_general_ci;";
    } else {
        $createTable .= ") ENGINE=INNODB CHARSET=utf8 COLLATE=utf8_general_ci;";
    }
    $db->query({ query => $createTable });
}

sub addIndexTempTableMetricDayFirstLastValues {
    my $self = shift;
    my $db = $self->{"centstorage"};
    $db->query({ query => "ALTER TABLE " . $self->{name_firstlast_tmp} . " ADD INDEX (`id_metric`)" });
}

sub addIndexTempTableMetricMinMaxAvgValues {
    my $self = shift;
    my $granularity = shift;
    my $db = $self->{"centstorage"};
    my $index = "id_metric";
    if ($granularity eq "hour") {
        $index .= ", valueTime";
    }
    my $query = "ALTER TABLE " . $self->{name_minmaxavg_tmp} . " ADD INDEX (" . $index . ")";
    $db->query({ query => $query });
}

sub createTempTableCtimeMinMaxValues {
    my ($self, $useMemory) = @_;
    my $db = $self->{"centstorage"};
    $db->query({ query => "DROP TABLE IF EXISTS `" . $self->{name_minmaxctime_tmp} . "`" });
    my $createTable = " CREATE TABLE `" . $self->{name_minmaxctime_tmp} . "` (";
    $createTable .= " min_val INT NULL,";
    $createTable .= " max_val INT NULL,";
    $createTable .= " id_metric INT NULL";
    if (defined($useMemory) && $useMemory eq "true") {
        $createTable .= ") ENGINE=MEMORY CHARSET=utf8 COLLATE=utf8_general_ci;";
    } else {
        $createTable .= ") ENGINE=INNODB CHARSET=utf8 COLLATE=utf8_general_ci;";
    }
    $db->query({ query => $createTable });
}

sub dropTempTableCtimeMinMaxValues {
    my $self = shift;
    my $db = $self->{"centstorage"};
    $db->query({ query => "DROP TABLE `" . $self->{name_minmaxctime_tmp} . "`" });
}

sub getFirstAndLastValues {
    my $self = shift;
    my $db = $self->{"centstorage"};
    
    my ($start_date, $end_date, $useMemory) = @_;
    
    $self->createTempTableCtimeMinMaxValues($useMemory);
    my $query = "INSERT INTO `" . $self->{name_minmaxctime_tmp} . "` SELECT min(ctime) as min_val, max(ctime) as max_val, id_metric ";
    $query .= " FROM `data_bin`";
    $query .= " WHERE ctime >= UNIX_TIMESTAMP(" . $start_date . ") AND ctime < UNIX_TIMESTAMP(" . $end_date . ")";
    $query .= " GROUP BY id_metric";
    $db->query({ query => $query });
    
    $self->createTempTableMetricDayFirstLastValues($useMemory);
    $query = "INSERT INTO " . $self->{name_firstlast_tmp} . " SELECT d.value as `first_value`, d2.value as `last_value`, d.id_metric";
    $query .= " FROM data_bin as d, data_bin as d2, " . $self->{name_minmaxctime_tmp} . " as db";
    $query .= " WHERE db.id_metric=d.id_metric AND db.min_val=d.ctime";
    $query .=         " AND db.id_metric=d2.id_metric AND db.max_val=d2.ctime";
    $query .= " GROUP BY db.id_metric";
    my $sth = $db->query({ query => $query });
    $self->addIndexTempTableMetricDayFirstLastValues();
    $self->dropTempTableCtimeMinMaxValues();
}

sub dailyPurge {
    my $self = shift;
    my $db = $self->{"centstorage"};
    my $logger = $self->{"logger"};
    my ($end) = @_;
    
    my $query = "DELETE FROM `data_bin` where ctime < UNIX_TIMESTAMP('" . $end . "')";
    $logger->writeLog("DEBUG", "[PURGE] [data_bin] purging data older than " . $end);
    $db->query({ query => $query });
}

1;
