<?php
/*
 * Copyright 2005-2015 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 * SVN : $URL$
 * SVN : $Id$
 *
 */

echo _("Home");
echo _("Monitoring");
echo _("Reporting");
echo _("Views");
echo _("Administration");
echo _("Configuration");
echo _("By Status");
echo _("By Host");
echo _("By Host Group");
echo _("Meta Services");
echo _("Monitoring Engine");
echo _("By Service Group");
echo _("Advanced Logs");
echo _("Hosts");
echo _("Host Groups");
echo _("Host Problems");
echo _("Unhandled Problems");
echo _("Services");
echo _("All Services");
echo _("Service Problems");
echo _("Details");
echo _("Summary");
echo _("Scheduling Queue");
echo _("Ok");
echo _("Warning");
echo _("Critical");
echo _("Unknown");
echo _("Problems");
echo _("Acknowledged");
echo _("Not Acknowledged");
echo _("Event Logs");
echo _("Downtime");
echo _("Comments");
echo _("All Logs");
echo _("Notifications");
echo _("Alerts");
echo _("Warnings");
echo _("Users");
echo _("Host Groups");
echo _("Templates");
echo _("Services by host");
echo _("Services by host group");
echo _("Service Groups");
echo _("SNMP Traps");
echo _("Manufacturer");
echo _("MIBs");
echo _("Contacts / Users");
echo _("Contact Templates");
echo _("Contact Groups");
echo _("Time Periods");
echo _("Commands");
echo _("Generate");
echo _("Load");
echo _("nagios.cfg");
echo _("resources");
echo _("cgi");
echo _("Checks");
echo _("Miscellaneous");
echo _("Plugins");
echo _("Options");
echo _("Modules");
echo _("Setup");
echo _("ACL");
echo _("Databases");
echo _("Sessions");
echo _("Server Status");
echo _("About");
echo _("Web Site");
echo _("Forum");
echo _("Wiki");
echo _("Bug Tracker");
echo _("Donate");
echo _("Support");
echo _("My Account");
echo _("Centreon");
echo _("Colors");
echo _("SNMP");
echo _("LDAP");
echo _("RRDTool");
echo _("Debug");
echo _("CSS");
echo _("CentStorage");
echo _("Manage");
echo _("Dashboard");
echo _("Host Groups");
echo _("Graphs");
echo _("All Graphs");
echo _("Curves");
echo _("Upgrade");
echo _("Check Version");
echo _("Pre-Update");
echo _("Categories");
echo _("ndo2db.cfg");
echo _("ndomod.cfg");
echo _("Pollers");
echo _("Optimize");
echo _("Escalations");
echo _("Dependencies");
echo _("Global Health");
echo _("Monitoring Engine Statistics");
echo _("Media");
echo _("Images");
echo _("Directories");
echo _("Access List");
echo _("Resources Access");
echo _("Access Groups");
echo _("Menus Access");
echo _("Tactical Overview");
echo _("Actions Access");
echo _("Logs");
echo _("Reload ACL");
echo _("Performance Info");
echo _("Process Info");
echo _("Process Control");
echo _("System Information");
echo _("Broker Statistics");
echo _("Custom Views");
echo _("Monitoring Engines");
echo _("Extra");
echo _("Services Grid");
echo _("Services by Hostgroup");
echo _("Services by Servicegroup");

/* List select on Administration -> Log */

echo _("command");
echo _("timeperiod");
echo _("contact");
echo _("contactgroup");
echo _("host");
echo _("hostgroup");
echo _("service");
echo _("servicegroup");
echo _("snmp traps");
echo _("escalation");
echo _("host dependency");
echo _("hostgroup dependency");
echo _("service dependency");
echo _("servicegroup dependency");


/* Views -> Graphs */

echo _("Yearly");


/* 
 * Centreon -> Centreon- > Centreon-Broker 
 */
echo _("Name");
echo _("Correlation file");
echo _("Retention file");
echo _("it's required");
echo _("Connection port");
echo _("Host to connect to");
echo _("Failover name");
echo _("Retry interval");
echo _("Buffering timeout");
echo _("Serialization protocol");
echo _("Enable TLS encryption");
echo _("Auto");
echo _("No");
echo _("Yes");
echo _("Private key file. ");
echo _("Public certificate");
echo _("Trusted CA's certificate");
echo _("Enable negociation");
echo _("One peer retention");
echo _("Filter category");
echo _("Available");
echo _("Selected");
echo _("Compression (zlib)");
echo _("Compression level");
echo _("Compression buffer size");
echo _("File path");
echo _("Maximum size of file");
echo _("Name of the logger");
echo _("Configuration messages");
echo _("Debug messages");
echo _("Error messages");
echo _("Informational messages ");
echo _("Logging level");
echo _("Max file size in bytes");
echo _("RRD file directory for metrics");
echo _("RRD file directory for statuses");
echo _("TCP port");
echo _("Unix socket");
echo _("Write metrics");
echo _("Write status");
echo _("Interval length");
echo _("RRD length");
echo _("DB type");
echo _("DB host");
echo _("DB port");
echo _("DB user");
echo _("DB password");
echo _("DB name");
echo _("Maximum queries per transaction");
echo _("Transaction commit timeout");
echo _("Replication enabled");
echo _("Rebuild check interval in seconds");
echo _("Store performance data in data_bin");
echo _("Insert in index data");
echo _("File for Centeron Broker statistics");
echo _("Cleanup check interval");
echo _("Instance timeout");

echo _("Centreon Broker information");
echo _("Centreon-Broker Correlation");
echo _("Centreon-Broker Input");
echo _("Centreon-Broker Logger");
echo _("Centreon-Broker Output");
echo _("Centreon-Broker Stats");
echo _("Centreon-Broker Temporary");



