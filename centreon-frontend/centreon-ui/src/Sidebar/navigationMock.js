export default [
  {
    label: "Home",
    menu_id: "Home",
    url: "./include/home/home.php",
    active: true,
    color: "2B9E93",
    icon: "home",
    children: [
      {
        id: "103",
        label: "Custom Views",
        url: "./include/home/customViews/index.php",
        active: false,
        groups: [],
        options: null,
        is_react: "0"
      }
    ],
    options: null,
    is_react: "0"
  },
  {
    label: "Monitoring",
    menu_id: "Monitoring",
    url: null,
    active: false,
    color: "85B446",
    icon: "monitoring",
    children: [
      {
        id: "202",
        label: "Status Details",
        url: null,
        active: false,
        groups: [
          {
            label: "By Status",
            children: [
              {
                id: "20201",
                label: "Services",
                url: "./include/monitoring/status/monitoringService.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "20202",
                label: "Hosts",
                url: "./include/monitoring/status/monitoringHost.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "20203",
                label: "Services Grid",
                url: "./include/monitoring/status/monitoringService.php",
                active: false,
                options: "&o=svcOV_pb",
                is_react: "0"
              },
              {
                id: "20204",
                label: "Services by Hostgroup",
                url: "./include/monitoring/status/monitoringService.php",
                active: false,
                options: "&o=svcOVHG_pb",
                is_react: "0"
              },
              {
                id: "20209",
                label: "Services by Servicegroup",
                url: "./include/monitoring/status/monitoringService.php",
                active: false,
                options: "&o=svcOVSG_pb",
                is_react: "0"
              },
              {
                id: "20212",
                label: "Hostgroups Summary",
                url: "./include/monitoring/status/monitoringHostGroup.php",
                active: false,
                options: "&o=hg",
                is_react: "0"
              }
            ]
          }
        ],
        options: null,
        is_react: "0"
      },
      {
        id: "204",
        label: "Performances",
        url: "",
        active: false,
        groups: [
          {
            label: "Main Menu",
            children: [
              {
                id: "20401",
                label: "Graphs",
                url: "./include/views/graphs/graphs.php",
                active: false,
                options: null,
                is_react: "0"
              }
            ]
          },
          {
            label: "Parameters",
            children: [
              {
                id: "20404",
                label: "Templates",
                url: "./include/views/graphTemplates/graphTemplates.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "20405",
                label: "Curves",
                url:
                  "./include/views/componentTemplates/componentTemplates.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "20408",
                label: "Virtual Metrics",
                url: "./include/views/virtualMetrics/virtualMetrics.php",
                active: false,
                options: null,
                is_react: "0"
              }
            ]
          }
        ],
        options: null,
        is_react: "0"
      },
      {
        id: "288",
        label: "Map",
        url: "./modules/centreon-map4-web-client/index.php",
        active: false,
        groups: [],
        options: null,
        is_react: "0"
      },
      {
        id: "207",
        label: "Business Activity",
        url: "./modules/centreon-bam-server/core/dashboard/dashboard.php",
        active: false,
        groups: [
          {
            label: "Views",
            children: [
              {
                id: "20701",
                label: "Monitoring",
                url:
                  "./modules/centreon-bam-server/core/dashboard/dashboard.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "20702",
                label: "Reporting",
                url:
                  "./modules/centreon-bam-server/core/reporting/reporting.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "20703",
                label: "Logs",
                url: "./modules/centreon-bam-server/core/logs/logs.php",
                active: false,
                options: null,
                is_react: "0"
              }
            ]
          }
        ],
        options: null,
        is_react: "0"
      },
      {
        id: "210",
        label: "Downtimes",
        url: null,
        active: false,
        groups: [
          {
            label: "Main Menu",
            children: [
              {
                id: "21001",
                label: "Downtimes",
                url: "./include/monitoring/downtime/downtime.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "21003",
                label: "Recurrent downtimes",
                url: "./include/monitoring/recurrentDowntime/downtime.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "21002",
                label: "Comments",
                url: "./include/monitoring/comments/comments.php",
                active: false,
                options: null,
                is_react: "0"
              }
            ]
          }
        ],
        options: null,
        is_react: "0"
      },
      {
        id: "203",
        label: "Event Logs",
        url: "",
        active: false,
        groups: [
          {
            label: "Advanced Logs",
            children: [
              {
                id: "20301",
                label: "Event Logs",
                url: "./include/eventLogs/viewLog.php",
                active: false,
                options: null,
                is_react: "0"
              }
            ]
          }
        ],
        options: null,
        is_react: "0"
      }
    ],
    options: "",
    is_react: "0"
  },
  {
    label: "Reporting",
    menu_id: "Reporting",
    url: null,
    active: false,
    color: "E4932C",
    icon: "reporting",
    children: [
      {
        id: "307",
        label: "Dashboard",
        url: null,
        active: false,
        groups: [
          {
            label: "Dashboard",
            children: [
              {
                id: "30701",
                label: "Hosts",
                url: "./include/reporting/dashboard/viewHostLog.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "30703",
                label: "Host Groups",
                url: "./include/reporting/dashboard/viewHostGroupLog.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "30704",
                label: "Service Groups",
                url: "./include/reporting/dashboard/viewServicesGroupLog.php",
                active: false,
                options: null,
                is_react: "0"
              }
            ]
          }
        ],
        options: null,
        is_react: "0"
      },
      {
        id: "302",
        label: "Monitoring Business Intelligence",
        url: "./modules/centreon-bi-server/views/archives/archive.php",
        active: false,
        groups: [
          {
            label: "Views",
            children: [
              {
                id: "30201",
                label: "Report view",
                url: "./modules/centreon-bi-server/views/archives/archive.php",
                active: false,
                options: null,
                is_react: "0"
              }
            ]
          },
          {
            label: "Configuration",
            children: [
              {
                id: "30210",
                label: "Jobs",
                url:
                  "./modules/centreon-bi-server/configuration/generation/generation_task.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "30220",
                label: "Job groups",
                url:
                  "./modules/centreon-bi-server/configuration/generationGroups/groups.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "30230",
                label: "Report designs",
                url:
                  "./modules/centreon-bi-server/configuration/report/report.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "30234",
                label: "Report design groups",
                url:
                  "./modules/centreon-bi-server/configuration/reportGroups/reportGroups.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "30240",
                label: "Logos",
                url: "./modules/centreon-bi-server/configuration/logo/logo.php",
                active: false,
                options: null,
                is_react: "0"
              }
            ]
          },
          {
            label: "Administration",
            children: [
              {
                id: "30233",
                label: "Publication rules",
                url:
                  "./modules/centreon-bi-server/options/publications/publications.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "30232",
                label: "Trash",
                url: "./modules/centreon-bi-server/options/trash/trash.php",
                active: false,
                options: null,
                is_react: "0"
              }
            ]
          }
        ],
        options: null,
        is_react: "0"
      }
    ],
    options: null,
    is_react: "0"
  },
  {
    label: "Configuration",
    menu_id: "Configuration",
    url: null,
    active: false,
    color: "319ED5",
    icon: "configuration",
    children: [
      {
        id: "601",
        label: "Hosts",
        url: null,
        active: false,
        groups: [
          {
            label: "Hosts",
            children: [
              {
                id: "60101",
                label: "Hosts",
                url: "./include/configuration/configObject/host/host.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "60102",
                label: "Host Groups",
                url:
                  "./include/configuration/configObject/hostgroup/hostGroup.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "60103",
                label: "Templates",
                url:
                  "./include/configuration/configObject/host_template_model/hostTemplateModel.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "60104",
                label: "Categories",
                url:
                  "./include/configuration/configObject/host_categories/hostCategories.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "60130",
                label: "Discovery",
                url: "/configuration/hosts/discovery/jobs",
                active: false,
                options: null,
                is_react: "1"
              }
            ]
          }
        ],
        options: null,
        is_react: "0"
      },
      {
        id: "602",
        label: "Services",
        url: null,
        active: false,
        groups: [
          {
            label: "Main Menu",
            children: [
              {
                id: "60201",
                label: "Services by host",
                url:
                  "./include/configuration/configObject/service/serviceByHost.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "60202",
                label: "Services by host group",
                url:
                  "./include/configuration/configObject/service/serviceByHostGroup.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "60203",
                label: "Service Groups",
                url:
                  "./include/configuration/configObject/servicegroup/serviceGroup.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "60206",
                label: "Templates",
                url:
                  "./include/configuration/configObject/service_template_model/serviceTemplateModel.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "60209",
                label: "Categories",
                url:
                  "./include/configuration/configObject/service_categories/serviceCategories.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "60204",
                label: "Meta Services",
                url:
                  "./include/configuration/configObject/meta_service/metaService.php",
                active: false,
                options: null,
                is_react: "0"
              }
            ]
          },
          {
            label: "Auto Discovery",
            children: [
              {
                id: "60210",
                label: "Scan",
                url:
                  "./modules/centreon-autodiscovery-server/views/scan/index.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "60215",
                label: "Rules",
                url:
                  "./modules/centreon-autodiscovery-server/views/rules/index.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "60214",
                label: "Overview",
                url:
                  "./modules/centreon-autodiscovery-server/views/overview/index.php",
                active: false,
                options: null,
                is_react: "0"
              }
            ]
          }
        ],
        options: null,
        is_react: "0"
      },
      {
        id: "626",
        label: "Business Activity",
        url:
          "./modules/centreon-bam-server/core/configuration/group/configuration_ba_group.php",
        active: false,
        groups: [
          {
            label: "Management",
            children: [
              {
                id: "62604",
                label: "Business Views",
                url:
                  "./modules/centreon-bam-server/core/configuration/group/configuration_ba_group.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "62605",
                label: "Business Activity",
                url:
                  "./modules/centreon-bam-server/core/configuration/ba/configuration_ba.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "62606",
                label: "Indicators",
                url:
                  "./modules/centreon-bam-server/core/configuration/kpi/configuration_kpi.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "62611",
                label: "Boolean Rules",
                url:
                  "./modules/centreon-bam-server/core/configuration/boolean/configuration_boolean.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "62612",
                label: "Dependencies",
                url:
                  "./modules/centreon-bam-server/core/configuration/dependencies/configuration_dependencies.php",
                active: false,
                options: null,
                is_react: "0"
              }
            ]
          },
          {
            label: "Options",
            children: [
              {
                id: "62607",
                label: "Default Settings",
                url:
                  "./modules/centreon-bam-server/core/options/general/general.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "62608",
                label: "User Settings",
                url: "./modules/centreon-bam-server/core/options/user/user.php",
                active: false,
                options: null,
                is_react: "0"
              }
            ]
          },
          {
            label: "Help",
            children: [
              {
                id: "62610",
                label: "Troubleshooter",
                url:
                  "./modules/centreon-bam-server/core/help/troubleshooter/troubleshooter.php",
                active: false,
                options: null,
                is_react: "0"
              }
            ]
          }
        ],
        options: null,
        is_react: "0"
      },
      {
        id: "603",
        label: "Users",
        url: null,
        active: false,
        groups: [
          {
            label: "Main Menu",
            children: [
              {
                id: "60301",
                label: "Contacts / Users",
                url: "./include/configuration/configObject/contact/contact.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "60302",
                label: "Contact Groups",
                url:
                  "./include/configuration/configObject/contactgroup/contactGroup.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "60304",
                label: "Time Periods",
                url:
                  "./include/configuration/configObject/timeperiod/timeperiod.php",
                active: false,
                options: null,
                is_react: "0"
              }
            ]
          }
        ],
        options: null,
        is_react: "0"
      },
      {
        id: "608",
        label: "Commands",
        url: null,
        active: false,
        groups: [
          {
            label: "Main Menu",
            children: [
              {
                id: "60801",
                label: "Checks",
                url: "./include/configuration/configObject/command/command.php",
                active: false,
                options: "&type=2",
                is_react: "0"
              },
              {
                id: "60802",
                label: "Notifications",
                url: "./include/configuration/configObject/command/command.php",
                active: false,
                options: "&type=1",
                is_react: "0"
              },
              {
                id: "60803",
                label: "Miscellaneous",
                url: "./include/configuration/configObject/command/command.php",
                active: false,
                options: "&type=3",
                is_react: "0"
              }
            ]
          }
        ],
        options: null,
        is_react: "0"
      },
      {
        id: "604",
        label: "Notifications",
        url: null,
        active: false,
        groups: [
          {
            label: "Escalations",
            children: [
              {
                id: "60401",
                label: "Escalations",
                url:
                  "./include/configuration/configObject/escalation/escalation.php",
                active: false,
                options: null,
                is_react: "0"
              }
            ]
          },
          {
            label: "Dependencies",
            children: [
              {
                id: "60407",
                label: "Hosts",
                url:
                  "./include/configuration/configObject/host_dependency/hostDependency.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "60408",
                label: "Host Groups",
                url:
                  "./include/configuration/configObject/hostgroup_dependency/hostGroupDependency.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "60409",
                label: "Services",
                url:
                  "./include/configuration/configObject/service_dependency/serviceDependency.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "60410",
                label: "Service Groups",
                url:
                  "./include/configuration/configObject/servicegroup_dependency/serviceGroupDependency.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "60411",
                label: "Meta Services",
                url:
                  "./include/configuration/configObject/metaservice_dependency/MetaServiceDependency.php",
                active: false,
                options: null,
                is_react: "0"
              }
            ]
          }
        ],
        options: null,
        is_react: "0"
      },
      {
        id: "617",
        label: "SNMP Traps",
        url: null,
        active: false,
        groups: [
          {
            label: "SNMP Traps",
            children: [
              {
                id: "61701",
                label: "SNMP Traps",
                url: "./include/configuration/configObject/traps/traps.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "61702",
                label: "Manufacturer",
                url:
                  "./include/configuration/configObject/traps-manufacturer/mnftr.php",
                active: false,
                options: null,
                is_react: "0"
              }
            ]
          }
        ],
        options: null,
        is_react: "0"
      },
      {
        id: "650",
        label: "Plugin Packs",
        url: "./modules/centreon-pp-manager/core/manager/main.php",
        active: false,
        groups: [],
        options: null,
        is_react: "0"
      },
      {
        id: "609",
        label: "Pollers",
        url: null,
        active: false,
        groups: [
          {
            label: "Main Menu",
            children: [
              {
                id: "60901",
                label: "Pollers",
                url: "./include/configuration/configServers/servers.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "60903",
                label: "Engine configuration",
                url: "./include/configuration/configNagios/nagios.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "60909",
                label: "Broker configuration",
                url:
                  "./include/configuration/configCentreonBroker/centreon-broker.php",
                active: false,
                options: null,
                is_react: "0"
              }
            ]
          }
        ],
        options: null,
        is_react: "0"
      }
    ],
    options: null,
    is_react: "0"
  },
  {
    label: "Administration",
    menu_id: "Administration",
    url: null,
    active: false,
    color: "17387B",
    icon: "administration",
    children: [
      {
        id: "501",
        label: "Parameters",
        url: "./include/options/oreon/myAccount/formMyAccount.php",
        active: false,
        groups: [
          {
            label: "Main Menu",
            children: [
              {
                id: "50110",
                label: "Centreon UI",
                url: "./include/Administration/parameters/parameters.php",
                active: false,
                options: "&o=general",
                is_react: "0"
              },
              {
                id: "50111",
                label: "Monitoring",
                url: "./include/Administration/parameters/parameters.php",
                active: false,
                options: "&o=engine",
                is_react: "0"
              },
              {
                id: "50104",
                label: "My Account",
                url: "./include/Administration/myAccount/formMyAccount.php",
                active: false,
                options: "&o=c",
                is_react: "0"
              },
              {
                id: "50113",
                label: "LDAP",
                url: "./include/Administration/parameters/parameters.php",
                active: false,
                options: "&o=ldap",
                is_react: "0"
              },
              {
                id: "50114",
                label: "RRDTool",
                url: "./include/Administration/parameters/parameters.php",
                active: false,
                options: "&o=rrdtool",
                is_react: "0"
              },
              {
                id: "50115",
                label: "Debug",
                url: "./include/Administration/parameters/parameters.php",
                active: false,
                options: "&o=debug",
                is_react: "0"
              }
            ]
          },
          {
            label: "Performance Management",
            children: [
              {
                id: "50118",
                label: "Options",
                url: "./include/Administration/parameters/parameters.php",
                active: false,
                options: "&o=storage",
                is_react: "0"
              }
            ]
          },
          {
            label: "Media",
            children: [
              {
                id: "50102",
                label: "Images",
                url: "./include/options/media/images/images.php",
                active: false,
                options: null,
                is_react: "0"
              }
            ]
          }
        ],
        options: "&o=c",
        is_react: "0"
      },
      {
        id: "502",
        label: "ACL",
        url: null,
        active: false,
        groups: [
          {
            label: "Access List",
            children: [
              {
                id: "50203",
                label: "Access Groups",
                url: "./include/options/accessLists/groupsACL/groupsConfig.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "50201",
                label: "Menus Access",
                url: "./include/options/accessLists/menusACL/menusAccess.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "50202",
                label: "Resources Access",
                url:
                  "./include/options/accessLists/resourcesACL/resourcesAccess.php",
                active: false,
                options: null,
                is_react: "0"
              },
              {
                id: "50204",
                label: "Actions Access",
                url:
                  "./include/options/accessLists/actionsACL/actionsConfig.php",
                active: false,
                options: null,
                is_react: "0"
              }
            ]
          }
        ],
        options: null,
        is_react: "0"
      },
      {
        id: "508",
        label: "Logs",
        url: "./include/Administration/configChangelog/viewLogs.php",
        active: false,
        groups: [
          {
            label: "Main Menu",
            children: [
              {
                id: "50801",
                label: "Configuration",
                url: "./include/Administration/configChangelog/viewLogs.php",
                active: false,
                options: null,
                is_react: "0"
              }
            ]
          }
        ],
        options: null,
        is_react: "0"
      },
      {
        id: "506",
        label: "About",
        url: "./include/Administration/about/about.php",
        active: false,
        groups: [],
        options: null,
        is_react: "0"
      }
    ],
    options: "&o=c",
    is_react: "0"
  }
];
