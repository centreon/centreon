export default [
    {
      page: '1',
      label: 'Home',
      menu_id: 'Home',
      url: './include/home/home.php',
      color: '2B9E93',
      icon: 'home',
      children: [
        {
          page: '103',
          label: 'Custom Views',
          url: './include/home/customViews/index.php',
          groups: [],
          options: null,
          is_react: false
        }
      ],
      options: null,
      is_react: false
    },
    {
      page: '2',
      label: 'Monitoring',
      menu_id: 'Monitoring',
      url: null,
      color: '85B446',
      icon: 'monitoring',
      children: [
        {
          page: '202',
          label: 'Status Details',
          url: null,
          groups: [
            {
              label: 'By Status',
              children: [
                {
                  page: '20201',
                  label: 'Services',
                  url: './include/monitoring/status/monitoringService.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '20202',
                  label: 'Hosts',
                  url: './include/monitoring/status/monitoringHost.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '20204',
                  label: 'Services Grid',
                  url: './include/monitoring/status/monitoringService.php',
                  options: '&o=svcOV',
                  is_react: false
                },
                {
                  page: '20209',
                  label: 'Services by Hostgroup',
                  url: './include/monitoring/status/monitoringService.php',
                  options: '&o=svcOVHG',
                  is_react: false
                },
                {
                  page: '20212',
                  label: 'Services by Servicegroup',
                  url: './include/monitoring/status/monitoringService.php',
                  options: '&o=svcOVSG',
                  is_react: false
                },
                {
                  page: '20203',
                  label: 'Hostgroups Summary',
                  url: './include/monitoring/status/monitoringHostGroup.php',
                  options: '&o=hg',
                  is_react: false
                }
              ]
            }
          ],
          options: null,
          is_react: false
        },
        {
          page: '204',
          label: 'Performances',
          url: '',
          groups: [
            {
              label: 'Main Menu',
              children: [
                {
                  page: '20401',
                  label: 'Graphs',
                  url: './include/views/graphs/graphs.php',
                  options: null,
                  is_react: false
                }
              ]
            },
            {
              label: 'Parameters',
              children: [
                {
                  page: '20404',
                  label: 'Templates',
                  url: './include/views/graphTemplates/graphTemplates.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '20405',
                  label: 'Curves',
                  url: './include/views/componentTemplates/componentTemplates.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '20408',
                  label: 'Virtual Metrics',
                  url: './include/views/virtualMetrics/virtualMetrics.php',
                  options: null,
                  is_react: false
                }
              ]
            }
          ],
          options: null,
          is_react: false
        },
        {
          page: '207',
          label: 'Business Activity',
          url: './modules/centreon-bam-server/core/dashboard/dashboard.php',
          groups: [
            {
              label: 'Views',
              children: [
                {
                  page: '20701',
                  label: 'Monitoring',
                  url: './modules/centreon-bam-server/core/dashboard/dashboard.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '20702',
                  label: 'Reporting',
                  url: './modules/centreon-bam-server/core/reporting/reporting.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '20703',
                  label: 'Logs',
                  url: './modules/centreon-bam-server/core/logs/logs.php',
                  options: null,
                  is_react: false
                }
              ]
            }
          ],
          options: null,
          is_react: false
        },
        {
          page: '210',
          label: 'Downtimes',
          url: null,
          groups: [
            {
              label: 'Main Menu',
              children: [
                {
                  page: '21001',
                  label: 'Downtimes',
                  url: './include/monitoring/downtime/downtime.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '21003',
                  label: 'Recurrent downtimes',
                  url: './include/monitoring/recurrentDowntime/downtime.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '21002',
                  label: 'Comments',
                  url: './include/monitoring/comments/comments.php',
                  options: null,
                  is_react: false
                }
              ]
            }
          ],
          options: null,
          is_react: false
        },
        {
          page: '203',
          label: 'Event Logs',
          url: '',
          groups: [
            {
              label: 'Advanced Logs',
              children: [
                {
                  page: '20301',
                  label: 'Event Logs',
                  url: './include/eventLogs/viewLog.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '20302',
                  label: 'System Logs',
                  url: './include/eventLogs/viewLog.php',
                  options: '&engine=true',
                  is_react: false
                }
              ]
            }
          ],
          options: null,
          is_react: false
        }
      ],
      options: '',
      is_react: false
    },
    {
      page: '3',
      label: 'Reporting',
      menu_id: 'Reporting',
      url: null,
      color: 'E4932C',
      icon: 'reporting',
      children: [
        {
          page: '307',
          label: 'Dashboard',
          url: null,
          groups: [
            {
              label: 'Dashboard',
              children: [
                {
                  page: '30701',
                  label: 'Hosts',
                  url: './include/reporting/dashboard/viewHostLog.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '30703',
                  label: 'Host Groups',
                  url: './include/reporting/dashboard/viewHostGroupLog.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '30704',
                  label: 'Service Groups',
                  url: './include/reporting/dashboard/viewServicesGroupLog.php',
                  options: null,
                  is_react: false
                }
              ]
            }
          ],
          options: null,
          is_react: false
        }
      ],
      options: null,
      is_react: false
    },
    {
      page: '6',
      label: 'Configuration',
      menu_id: 'Configuration',
      url: null,
      color: '319ED5',
      icon: 'configuration',
      children: [
        {
          page: '601',
          label: 'Hosts',
          url: null,
          groups: [
            {
              label: 'Hosts',
              children: [
                {
                  page: '60101',
                  label: 'Hosts',
                  url: './include/configuration/configObject/host/host.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '60102',
                  label: 'Host Groups',
                  url: './include/configuration/configObject/hostgroup/hostGroup.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '60103',
                  label: 'Templates',
                  url: './include/configuration/configObject/host_template_model/hostTemplateModel.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '60104',
                  label: 'Categories',
                  url: './include/configuration/configObject/host_categories/hostCategories.php',
                  options: null,
                  is_react: false
                }
              ]
            }
          ],
          options: null,
          is_react: false
        },
        {
          page: '602',
          label: 'Services',
          url: null,
          groups: [
            {
              label: 'Main Menu',
              children: [
                {
                  page: '60201',
                  label: 'Services by host',
                  url: './include/configuration/configObject/service/serviceByHost.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '60202',
                  label: 'Services by host group',
                  url: './include/configuration/configObject/service/serviceByHostGroup.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '60203',
                  label: 'Service Groups',
                  url: './include/configuration/configObject/servicegroup/serviceGroup.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '60206',
                  label: 'Templates',
                  url: './include/configuration/configObject/service_template_model/serviceTemplateModel.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '60209',
                  label: 'Categories',
                  url: './include/configuration/configObject/service_categories/serviceCategories.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '60204',
                  label: 'Meta Services',
                  url: './include/configuration/configObject/meta_service/metaService.php',
                  options: null,
                  is_react: false
                }
              ]
            }
          ],
          options: null,
          is_react: false
        },
        {
          page: '626',
          label: 'Business Activity',
          url: './modules/centreon-bam-server/core/configuration/group/configuration_ba.php',
          groups: [
            {
              label: 'Management',
              children: [
                {
                  page: '62605',
                  label: 'Business Activity',
                  url: './modules/centreon-bam-server/core/configuration/ba/configuration_ba.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '62604',
                  label: 'Business Views',
                  url: './modules/centreon-bam-server/core/configuration/group/configuration_ba_group.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '62606',
                  label: 'Indicators',
                  url: './modules/centreon-bam-server/core/configuration/kpi/configuration_kpi.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '62611',
                  label: 'Boolean Rules',
                  url: './modules/centreon-bam-server/core/configuration/boolean/configuration_boolean.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '62612',
                  label: 'Dependencies',
                  url: './modules/centreon-bam-server/core/configuration/dependencies/configuration_dependencies.php',
                  options: null,
                  is_react: false
                }
              ]
            },
            {
              label: 'Options',
              children: [
                {
                  page: '62607',
                  label: 'Default Settings',
                  url: './modules/centreon-bam-server/core/options/general/general.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '62608',
                  label: 'User Settings',
                  url: './modules/centreon-bam-server/core/options/user/user.php',
                  options: null,
                  is_react: false
                }
              ]
            },
            {
              label: 'Help',
              children: [
                {
                  page: '62610',
                  label: 'Troubleshooter',
                  url: './modules/centreon-bam-server/core/help/troubleshooter/troubleshooter.php',
                  options: null,
                  is_react: false
                }
              ]
            }
          ],
          options: null,
          is_react: false
        },
        {
          page: '603',
          label: 'Users',
          url: null,
          groups: [
            {
              label: 'Main Menu',
              children: [
                {
                  page: '60301',
                  label: 'Contacts / Users',
                  url: './include/configuration/configObject/contact/contact.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '60306',
                  label: 'Contact Templates',
                  url: './include/configuration/configObject/contact_template_model/contact_template.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '60302',
                  label: 'Contact Groups',
                  url: './include/configuration/configObject/contactgroup/contactGroup.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '60304',
                  label: 'Time Periods',
                  url: './include/configuration/configObject/timeperiod/timeperiod.php',
                  options: null,
                  is_react: false
                }
              ]
            }
          ],
          options: null,
          is_react: false
        },
        {
          page: '608',
          label: 'Commands',
          url: null,
          groups: [
            {
              label: 'Main Menu',
              children: [
                {
                  page: '60801',
                  label: 'Checks',
                  url: './include/configuration/configObject/command/command.php',
                  options: '&type=2',
                  is_react: false
                },
                {
                  page: '60802',
                  label: 'Notifications',
                  url: './include/configuration/configObject/command/command.php',
                  options: '&type=1',
                  is_react: false
                },
                {
                  page: '60807',
                  label: 'Discovery',
                  url: './include/configuration/configObject/command/command.php',
                  options: '&type=4',
                  is_react: false
                },
                {
                  page: '60803',
                  label: 'Miscellaneous',
                  url: './include/configuration/configObject/command/command.php',
                  options: '&type=3',
                  is_react: false
                },
                {
                  page: '60806',
                  label: 'Connectors',
                  url: './include/configuration/configObject/connector/connector.php',
                  options: null,
                  is_react: false
                }
              ]
            }
          ],
          options: null,
          is_react: false
        },
        {
          page: '604',
          label: 'Notifications',
          url: null,
          groups: [
            {
              label: 'Escalations',
              children: [
                {
                  page: '60401',
                  label: 'Escalations',
                  url: './include/configuration/configObject/escalation/escalation.php',
                  options: null,
                  is_react: false
                }
              ]
            },
            {
              label: 'Dependencies',
              children: [
                {
                  page: '60407',
                  label: 'Hosts',
                  url: './include/configuration/configObject/host_dependency/hostDependency.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '60408',
                  label: 'Host Groups',
                  url: './include/configuration/configObject/hostgroup_dependency/hostGroupDependency.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '60409',
                  label: 'Services',
                  url: './include/configuration/configObject/service_dependency/serviceDependency.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '60410',
                  label: 'Service Groups',
                  url: './include/configuration/configObject/servicegroup_dependency/serviceGroupDependency.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '60411',
                  label: 'Meta Services',
                  url: './include/configuration/configObject/metaservice_dependency/MetaServiceDependency.php',
                  options: null,
                  is_react: false
                }
              ]
            }
          ],
          options: null,
          is_react: false
        },
        {
          page: '617',
          label: 'SNMP Traps',
          url: null,
          groups: [
            {
              label: 'SNMP Traps',
              children: [
                {
                  page: '61701',
                  label: 'SNMP Traps',
                  url: './include/configuration/configObject/traps/traps.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '61702',
                  label: 'Manufacturer',
                  url: './include/configuration/configObject/traps-manufacturer/mnftr.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '61705',
                  label: 'Group',
                  url: './include/configuration/configObject/traps-groups/groups.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '61703',
                  label: 'MIBs',
                  url: './include/configuration/configObject/traps-mibs/mibs.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '61704',
                  label: 'Generate',
                  url: './include/configuration/configGenerateTraps/generateTraps.php',
                  options: null,
                  is_react: false
                }
              ]
            }
          ],
          options: null,
          is_react: false
        },
        {
          page: '650',
          label: 'Plugin Packs',
          url: null,
          groups: [
            {
              label: 'Plugin Packs',
              children: [
                {
                  page: '65001',
                  label: 'Manager',
                  url: './modules/centreon-pp-manager/core/manager/main.php',
                  options: null,
                  is_react: false
                }
              ]
            }
          ],
          options: null,
          is_react: false
        },
        {
          page: '609',
          label: 'Pollers',
          url: null,
          groups: [
            {
              label: 'Main Menu',
              children: [
                {
                  page: '60901',
                  label: 'Pollers',
                  url: './include/configuration/configServers/servers.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '60903',
                  label: 'Engine configuration',
                  url: './include/configuration/configNagios/nagios.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '60909',
                  label: 'Broker configuration',
                  url: './include/configuration/configCentreonBroker/centreon-broker.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '60904',
                  label: 'Resources',
                  url: './include/configuration/configResources/resources.php',
                  options: null,
                  is_react: false
                }
              ]
            }
          ],
          options: null,
          is_react: false
        },
        {
          page: '610',
          label: 'Knowledge Base',
          url: null,
          groups: [
            {
              label: 'Knowledge Base',
              children: [
                {
                  page: '61001',
                  label: 'Hosts',
                  url: './include/configuration/configKnowledge/display-hosts.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '61002',
                  label: 'Services',
                  url: './include/configuration/configKnowledge/display-services.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '61003',
                  label: 'Host Templates',
                  url: './include/configuration/configKnowledge/display-hostTemplates.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '61004',
                  label: 'Service Templates',
                  url: './include/configuration/configKnowledge/display-serviceTemplates.php',
                  options: null,
                  is_react: false
                }
              ]
            }
          ],
          options: null,
          is_react: false
        }
      ],
      options: null,
      is_react: false
    },
    {
      page: '5',
      label: 'Administration',
      menu_id: 'Administration',
      url: null,
      color: '17387B',
      icon: 'administration',
      children: [
        {
          page: '501',
          label: 'Parameters',
          url: './include/options/oreon/myAccount/formMyAccount.php',
          groups: [
            {
              label: 'Main Menu',
              children: [
                {
                  page: '50110',
                  label: 'Centreon UI',
                  url: './include/Administration/parameters/parameters.php',
                  options: '&o=general',
                  is_react: false
                },
                {
                  page: '50111',
                  label: 'Monitoring',
                  url: './include/Administration/parameters/parameters.php',
                  options: '&o=engine',
                  is_react: false
                },
                {
                  page: '50117',
                  label: 'CentCore',
                  url: './include/Administration/parameters/parameters.php',
                  options: '&o=centcore',
                  is_react: false
                },
                {
                  page: '50104',
                  label: 'My Account',
                  url: './include/Administration/myAccount/formMyAccount.php',
                  options: '&o=c',
                  is_react: false
                },
                {
                  page: '50113',
                  label: 'LDAP',
                  url: './include/Administration/parameters/parameters.php',
                  options: '&o=ldap',
                  is_react: false
                },
                {
                  page: '50114',
                  label: 'RRDTool',
                  url: './include/Administration/parameters/parameters.php',
                  options: '&o=rrdtool',
                  is_react: false
                },
                {
                  page: '50115',
                  label: 'Debug',
                  url: './include/Administration/parameters/parameters.php',
                  options: '&o=debug',
                  is_react: false
                },
                {
                  page: '50133',
                  label: 'Knowledge Base',
                  url: './include/Administration/parameters/parameters.php',
                  options: '&o=knowledgeBase',
                  is_react: false
                },
                {
                  page: '50165',
                  label: 'Backup',
                  url: './include/Administration/parameters/parameters.php',
                  options: '&o=backup',
                  is_react: false
                }
              ]
            },
            {
              label: 'Performance Management',
              children: [
                {
                  page: '50118',
                  label: 'Options',
                  url: './include/Administration/parameters/parameters.php',
                  options: '&o=storage',
                  is_react: false
                },
                {
                  page: '50119',
                  label: 'Data',
                  url: './include/Administration/performance/manageData.php',
                  options: null,
                  is_react: false
                }
              ]
            },
            {
              label: 'Media',
              children: [
                {
                  page: '50102',
                  label: 'Images',
                  url: './include/options/media/images/images.php',
                  options: null,
                  is_react: false
                }
              ]
            }
          ],
          options: '&o=c',
          is_react: false
        },
        {
          page: '502',
          label: 'ACL',
          url: null,
          groups: [
            {
              label: 'Access List',
              children: [
                {
                  page: '50203',
                  label: 'Access Groups',
                  url: './include/options/accessLists/groupsACL/groupsConfig.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '50201',
                  label: 'Menus Access',
                  url: './include/options/accessLists/menusACL/menusAccess.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '50202',
                  label: 'Resources Access',
                  url: './include/options/accessLists/resourcesACL/resourcesAccess.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '50204',
                  label: 'Actions Access',
                  url: './include/options/accessLists/actionsACL/actionsConfig.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '50205',
                  label: 'Reload ACL',
                  url: './include/options/accessLists/reloadACL/reloadACL.php',
                  options: null,
                  is_react: false
                }
              ]
            }
          ],
          options: null,
          is_react: false
        },
        {
          page: '507',
          label: 'Extensions',
          url: null,
          groups: [
            {
              label: 'Extensions',
              children: [
                {
                  page: '50709',
                  label: 'Manager',
                  url: '/administration/extensions/manager',
                  options: null,
                  is_react: true
                },
                {
                  page: '50710',
                  label: 'Iframe',
                  url: '/iframe.html',
                  options: null,
                  is_react: true
                },
                {
                  page: '50707',
                  label: 'Subscription',
                  url: './modules/centreon-license-manager/frontend/app/index.php',
                  options: null,
                  is_react: false
                }
              ]
            }
          ],
          options: null,
          is_react: false
        },
        {
          page: '508',
          label: 'Logs',
          url: './include/Administration/configChangelog/viewLogs.php',
          groups: [],
          options: null,
          is_react: false
        },
        {
          page: '504',
          label: 'Sessions',
          url: './include/options/session/connected_user.php',
          groups: [],
          options: null,
          is_react: false
        },
        {
          page: '505',
          label: 'Platform Status',
          url: './include/Administration/brokerPerformance/brokerPerformance.php',
          groups: [
            {
              label: 'Main Menu',
              children: [
                {
                  page: '50501',
                  label: 'Broker Statistics',
                  url: './include/Administration/brokerPerformance/brokerPerformance.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '50502',
                  label: 'Engine Statistics',
                  url: './include/Administration/corePerformance/nagiosStats.php',
                  options: null,
                  is_react: false
                },
                {
                  page: '50503',
                  label: 'Databases',
                  url: './include/options/db/viewDBInfos.php',
                  options: null,
                  is_react: false
                }
              ]
            }
          ],
          options: null,
          is_react: false
        },
        {
          page: '506',
          label: 'About',
          url: './include/Administration/about/about.php',
          groups: [],
          options: null,
          is_react: false
        }
      ],
      options: '&o=c',
      is_react: false
    }
  ]