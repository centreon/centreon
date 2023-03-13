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

package gorgone::modules::centreon::mbi::etlworkers::dimensions::main;

use strict;
use warnings;

use IO::Socket::INET;

use gorgone::modules::centreon::mbi::libs::centreon::Host;
use gorgone::modules::centreon::mbi::libs::centreon::HostGroup;
use gorgone::modules::centreon::mbi::libs::centreon::HostCategory;
use gorgone::modules::centreon::mbi::libs::centreon::ServiceCategory;
use gorgone::modules::centreon::mbi::libs::centreon::Service;
use gorgone::modules::centreon::mbi::libs::centreon::Timeperiod;
use gorgone::modules::centreon::mbi::libs::bi::BIHost;
use gorgone::modules::centreon::mbi::libs::bi::BIHostGroup;
use gorgone::modules::centreon::mbi::libs::bi::BIHostCategory;
use gorgone::modules::centreon::mbi::libs::bi::BIServiceCategory;
use gorgone::modules::centreon::mbi::libs::bi::BIService;
use gorgone::modules::centreon::mbi::libs::bi::BIMetric;
use gorgone::modules::centreon::mbi::libs::bi::Time;
use gorgone::modules::centreon::mbi::libs::bi::LiveService;
use gorgone::modules::centreon::mbi::libs::bi::DataQuality;

my ($time, $liveService, $host, $service);
my ($hostBI, $biHost, $hostCentreon, $biService, $timePeriod, $biMetric);
my ($biHostgroup, $biServicecategory, $biHostcategory, $hostgroup, $servicecategory, $hostcategory, $biDataQuality);

# Initialize objects for program
sub initVars {
    my ($etlwk, %options) = @_;

    # instance of 
    $host = gorgone::modules::centreon::mbi::libs::centreon::Host->new($etlwk->{messages}, $etlwk->{dbbi_centreon_con});
    $hostcategory = gorgone::modules::centreon::mbi::libs::centreon::HostCategory->new($etlwk->{messages}, $etlwk->{dbbi_centreon_con});
    $servicecategory = gorgone::modules::centreon::mbi::libs::centreon::ServiceCategory->new($etlwk->{messages}, $etlwk->{dbbi_centreon_con});
    $hostgroup = gorgone::modules::centreon::mbi::libs::centreon::HostGroup->new($etlwk->{messages}, $etlwk->{dbbi_centreon_con});
    $service = gorgone::modules::centreon::mbi::libs::centreon::Service->new($etlwk->{messages}, $etlwk->{dbbi_centreon_con});
    $timePeriod = gorgone::modules::centreon::mbi::libs::centreon::Timeperiod->new($etlwk->{messages}, $etlwk->{dbbi_centreon_con});
    $biHost = gorgone::modules::centreon::mbi::libs::bi::BIHost->new($etlwk->{messages}, $etlwk->{dbbi_centstorage_con});
    $biHostgroup = gorgone::modules::centreon::mbi::libs::bi::BIHostGroup->new($etlwk->{messages}, $etlwk->{dbbi_centstorage_con});
    $biHostcategory = gorgone::modules::centreon::mbi::libs::bi::BIHostCategory->new($etlwk->{messages}, $etlwk->{dbbi_centstorage_con});
    $biServicecategory = gorgone::modules::centreon::mbi::libs::bi::BIServiceCategory->new($etlwk->{messages}, $etlwk->{dbbi_centstorage_con});
    $biService = gorgone::modules::centreon::mbi::libs::bi::BIService->new($etlwk->{messages}, $etlwk->{dbbi_centstorage_con});
    $time = gorgone::modules::centreon::mbi::libs::bi::Time->new($etlwk->{messages}, $etlwk->{dbbi_centstorage_con});
    $liveService = gorgone::modules::centreon::mbi::libs::bi::LiveService->new($etlwk->{messages}, $etlwk->{dbbi_centstorage_con});
    $biMetric = gorgone::modules::centreon::mbi::libs::bi::BIMetric->new($etlwk->{messages}, $etlwk->{dbbi_centstorage_con});
    $biDataQuality = gorgone::modules::centreon::mbi::libs::bi::DataQuality->new($etlwk->{messages}, $etlwk->{dbbi_centreon_con});
}

# temporary method to list liveservices for job configuration in Centreon
sub copyLiveServicesToMonitoringDB {
    my ($etlwk, %options) = @_;

    return if ($etlwk->{dbmon_centstorage_con}->sameParams(%{$options{dbbi}->{centstorage}}) == 1);

    $etlwk->{dbmon_centstorage_con}->query({ query => "TRUNCATE TABLE mod_bi_liveservice" });
    my $sth = $etlwk->{dbbi_centstorage_con}->query({ query => "SELECT id, name, timeperiod_id FROM mod_bi_liveservice" });
    while (my $row = $sth->fetchrow_hashref()) {
        my $insertQuery = "INSERT INTO mod_bi_liveservice (id, name, timeperiod_id) VALUES (".
            $row->{'id'} . ",'" . $row->{name} . "'," . $row->{timeperiod_id} . ")";
        $etlwk->{dbmon_centstorage_con}->query({ query => $insertQuery });
    }
}

sub truncateDimensionTables {
    my ($etlwk, %options) = @_;
    
    if ($options{options}->{rebuild} == 1 && $options{options}->{nopurge} == 0) {
        $biHostgroup->truncateTable();
        $biHostcategory->truncateTable();
        $biServicecategory->truncateTable();
        $biHost->truncateTable();
        $biService->truncateTable();
        $biMetric->truncateTable();
        $time->truncateTable();
        $liveService->truncateTable();
    }
}

sub denormalizeDimensionsFromCentreon {
    my ($etlwk, %options) = @_;

    #set etlProperties for all dimensions object to be able to use it when filtering on hg/hc/sc
    $host->setEtlProperties($options{etlProperties});
    $hostcategory->setEtlProperties($options{etlProperties});
    $servicecategory->setEtlProperties($options{etlProperties});
    $hostgroup->setEtlProperties($options{etlProperties});
    $service->setEtlProperties($options{etlProperties});
    
    $etlwk->{messages}->writeLog("INFO", "Getting host properties from Centreon database");
    my $rows = $host->getHostGroupAndCategories();
    $etlwk->{messages}->writeLog("INFO", "Updating host dimension in Centstorage");
    if ($options{options}->{rebuild} == 1 && $options{options}->{nopurge} == 0) {
        $biHost->insert($rows);
    } else {
        $biHost->update($rows, $options{etlProperties}->{'tmp.storage.memory'});
    }

    $etlwk->{messages}->writeLog("INFO", "Getting hostgroup properties from Centreon database");
    $rows = $hostgroup->getAllEntries();
    $etlwk->{messages}->writeLog("INFO", "Updating hostgroup dimension in Centstorage");
    $biHostgroup->insert($rows);

    $etlwk->{messages}->writeLog("INFO", "Getting hostcategories properties from Centreon database");
    $rows = $hostcategory->getAllEntries();
    $etlwk->{messages}->writeLog("INFO", "Updating hostcategories dimension in Centstorage");
    $biHostcategory->insert($rows);

    $etlwk->{messages}->writeLog("INFO", "Getting servicecategories properties from Centreon database");
    $rows = $servicecategory->getAllEntries();
    $etlwk->{messages}->writeLog("INFO", "Updating servicecategories dimension in Centstorage");
    $biServicecategory->insert($rows);
    $etlwk->{messages}->writeLog("INFO", "Getting service properties from Centreon database");

    my $hostRows = $biHost->getHostsInfo();
    my $serviceRows = $service->getServicesWithHostAndCategory($hostRows);
    $etlwk->{messages}->writeLog("INFO", "Updating service dimension in Centstorage");
    if ($options{options}->{rebuild} == 1 && $options{options}->{nopurge} == 0) {
        $biService->insert($serviceRows);
    } else {
        $biService->update($serviceRows, $options{etlProperties}->{'tmp.storage.memory'});
    }

    if (!defined($options{etlProperties}->{'statistics.type'}) || $options{etlProperties}->{'statistics.type'} ne 'availability') {
        $etlwk->{messages}->writeLog("INFO", "Updating metric dimension in Centstorage");
        if ($options{options}->{rebuild} == 1 && $options{options}->{nopurge} == 0) {
            $biMetric->insert();
        } else {
            $biMetric->update($options{etlProperties}->{'tmp.storage.memory'});
        }
    }

    # Getting live services to calculate reporting by time range
    $etlwk->{messages}->writeLog("INFO", "Updating liveservice dimension in Centstorage");

    my $timeperiods = $timePeriod->getPeriods($options{etlProperties}->{'liveservices.availability'});
    $liveService->insertList($timeperiods);
    $timeperiods = $timePeriod->getPeriods($options{etlProperties}->{'liveservices.perfdata'});
    $liveService->insertList($timeperiods);
    $timeperiods = $timePeriod->getCentilePeriods();
    $liveService->insertList($timeperiods);    
}

sub insertCentileParamToBIStorage{
    my ($etlwk, %options) = @_;

    my %result;
    my $sth;

    #Insert potential missing time periods related to centile calculation in mod_bi_liveservices
    $sth = $etlwk->{dbbi_centreon_con}->query({ query => "SELECT tp_id, tp_name FROM timeperiod WHERE tp_id IN (SELECT timeperiod_id FROM mod_bi_options_centiles)" });
    while (my $row = $sth->fetchrow_hashref()) {
        $result{$row->{tp_id}} = $row->{tp_name};    
    }
    
    #If not time period is found in centile configuration, exit the function
    if (%result eq 0){
        $etlwk->{messages}->writeLog("INFO", "No configuration found for centile calculation");
        return;
    }
    $etlwk->{messages}->writeLog("INFO", "Updating centile properties");

    my $timeperiods = $timePeriod->getPeriods(\%result);
    $liveService->insertList($timeperiods);

    #In case of rebuild, delete all centile parameters
    if ($options{options}->{rebuild} == 1){
        $etlwk->{dbbi_centstorage_con}->query({ query => "TRUNCATE TABLE mod_bi_centiles" });
    }
    $sth = $etlwk->{dbbi_centreon_con}->query({ query => "select * from mod_bi_options_centiles" });
    while (my $row = $sth->fetchrow_hashref()) {
        my ($tpName,$liveServiceId) = $liveService->getLiveServicesByNameForTpId($row->{'timeperiod_id'});
        my $insertQuery = "INSERT IGNORE INTO mod_bi_centiles (id, centile_param, liveservice_id,tp_name) VALUES (".$row->{'id'}.",'".$row->{'centile_param'}."',".$liveServiceId.",'".$tpName."')";
        $etlwk->{dbbi_centstorage_con}->query({ query => $insertQuery });
    }
}

sub copyCentileToMonitoringDB {
    my ($etlwk, %options) = @_;

    return if ($etlwk->{dbmon_centstorage_con}->sameParams(%{$options{dbbi}->{centstorage}}) == 1);

    $etlwk->{dbmon_centstorage_con}->query({ query => "TRUNCATE TABLE mod_bi_centiles" });
    my $sth = $etlwk->{dbbi_centstorage_con}->query({ query => "SELECT id, centile_param, liveservice_id, tp_name FROM mod_bi_centiles" });
    while (my $row = $sth->fetchrow_hashref()) {
        my $insertQuery = "INSERT INTO mod_bi_centiles (id, centile_param, liveservice_id,tp_name) VALUES (".
            $row->{id} . ",'" . $row->{centile_param} . "'," . $row->{liveservice_id} . ",'" . $row->{tp_name} . "')";
        $etlwk->{dbmon_centstorage_con}->query({ query => $insertQuery });
    }
}

sub startCbisAclSync{
    my ($etlwk, %options) = @_;

    # create a connecting socket
    my $socket = new IO::Socket::INET(
        PeerHost => 'localhost',
        PeerPort => '1234',
        Proto => 'tcp'
    );

    if (!$socket){
        $etlwk->{messages}->writeLog("WARNING", "Can't start ACL synchronization, make sure CBIS is started on port 1234");
        return 0;
    }
    #die "[ERROR] Cannot connect to CBIS on port 1234" unless $socket;
    # XML ACL request
    my $req = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".
        "<data>\n".
        "  <action type=\"updateResourceAcl\">\n".
        "    <task success=\"true\" message=\"Synchronizing resources ACL. It may take few minutes.\" />\n".
        "  </action>\n".
        "</data>\n";
    $etlwk->{messages}->writeLog("INFO", "Send ACL synchronization signal to CBIS");
    my $size = $socket->send($req);

    # notify server that request has been sent
    shutdown($socket, 1);
    
    # receive a response of up to 1024 characters from server
    my $response = "";
    $socket->recv($response, 1024);
    $socket->close();
}

sub execute {
    my ($etlwk, %options) = @_;

    initVars($etlwk, %options);

    $biDataQuality->searchAndDeleteDuplicateEntries();
    if (!defined($options{options}->{centile}) || $options{options}->{centile} == 0) {
        truncateDimensionTables($etlwk, %options);
        denormalizeDimensionsFromCentreon($etlwk, %options);
        copyLiveServicesToMonitoringDB($etlwk, %options);
    }

    insertCentileParamToBIStorage($etlwk, %options);
    copyCentileToMonitoringDB($etlwk, %options);
    startCbisAclSync($etlwk, %options);
}

1;
