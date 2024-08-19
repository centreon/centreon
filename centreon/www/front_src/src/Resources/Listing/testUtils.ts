import type { Column } from '@centreon/ui';
import { PlatformFeatures } from '@centreon/ui-context';

import { resourcesToAcknowledgeAtom } from '../Actions/actionsAtoms';
import { Resource, ResourceType } from '../models';

import { identity, includes } from 'ramda';
import { defaultSelectedColumnIds, getColumns } from './columns';

export const columns = getColumns({
  actions: {
    resourcesToAcknowledgeAtom
  },
  t: identity
}) as Array<Column>;

const fillEntities = ({
  entityCount = 31,
  enableCriticalResource = false
}): Array<Resource> => {
  const defaultSeverityCode = enableCriticalResource ? 1 : 4;
  const defaultSeverityName = enableCriticalResource ? 'CRITICAL' : 'PENDING';

  return new Array(entityCount).fill(0).map((_, index) => ({
    duration: '1m',
    has_passive_checks_enabled: index % 8 === 0,
    id: index,
    information:
      index % 5 === 0 ? `Entity ${index}` : `Entity ${index}\n Line ${index}`,
    is_acknowledged: index % 2 === 0,
    is_in_downtime: index % 3 === 0,
    last_check: '1m',
    links: {
      endpoints: {
        acknowledgement: `/monitoring/acknowledgement/${index}`,
        details: 'endpoint',
        downtime: `/monitoring/downtime/${index}`,
        metrics: 'endpoint',
        performance_graph: index % 6 === 0 ? 'endpoint' : undefined,
        status_graph: index % 3 === 0 ? 'endpoint' : undefined,
        timeline: 'endpoint'
      },
      externals: {
        notes: {
          url: 'https://centreon.com'
        }
      },
      uris: {
        configuration: index % 7 === 0 ? 'uri' : undefined,
        logs: index % 4 === 0 ? 'uri' : undefined,
        reporting: index % 3 === 0 ? 'uri' : undefined
      }
    },
    name: `E${index}`,
    severity_level: index % 3 === 0 ? 1 : 2,
    short_type: index % 4 === 0 ? 's' : 'h',
    status: {
      name: index % 2 === 0 ? 'OK' : defaultSeverityName,
      severity_code: index % 2 === 0 ? 5 : defaultSeverityCode
    },
    tries: '1',
    type: index % 4 === 0 ? ResourceType.service : ResourceType.host,
    uuid: `${index}`
  }));
};

export const entities = fillEntities({});

export const retrievedListing = {
  meta: {
    limit: 10,
    page: 1,
    search: {},
    sort_by: {},
    total: entities.length
  },
  result: entities
};

export const entitiesWithCriticalResources = fillEntities({
  enableCriticalResource: true,
  entityCount: 2
});

export const retrievedListingWithCriticalResources = {
  meta: {
    limit: 10,
    page: 1,
    search: {},
    sort_by: {},
    total: entitiesWithCriticalResources.length
  },
  result: entitiesWithCriticalResources
};

export const getPlatformFeatures = ({
  enableTreeView = true,
  notification = false
}: {
  enableTreeView?: boolean;
  notification?: boolean;
}): PlatformFeatures => {
  return {
    featureFlags: {
      notification,
      resourceStatusTreeView: enableTreeView
    },
    isCloudPlatform: false
  };
};

export const fakeData = {
  meta: { limit: 10, page: 1, search: {}, sort_by: {}, total: 0 },
  result: []
};

export const columnToSort = columns
  .filter(({ sortable }) => sortable !== false)
  .filter(({ id }) => includes(id, defaultSelectedColumnIds));

export const retrievedListingByHosts = {
  meta: {
    limit: 30,
    page: 1,
    search: {},
    sort_by: {
      last_status_change: 'DESC',
      status_severity_code: 'DESC'
    },
    total: 8
  },
  result: [
    {
      alias: 'Monitoring Server',
      children: {
        resources: [
          {
            alias: null,
            duration: '9m 41s',
            fqdn: null,
            has_active_checks_enabled: true,
            has_passive_checks_enabled: false,
            icon: null,
            id: 19,
            information: '(Execute command failed)',
            is_acknowledged: false,
            is_in_downtime: true,
            is_notification_enabled: false,
            last_check: '2m 41s',
            last_status_change: '2023-09-21T16:57:35+02:00',
            links: {
              endpoints: {
                acknowledgement:
                  '/centreon/api/latest/monitoring/hosts/14/services/19/acknowledgements?limit=1',
                check:
                  '/centreon/api/latest/monitoring/hosts/14/services/19/check',
                details:
                  '/centreon/api/latest/monitoring/resources/hosts/14/services/19',
                downtime:
                  '/centreon/api/latest/monitoring/hosts/14/services/19/downtimes?search=%7B%22%24and%22:%5B%7B%22start_time%22:%7B%22%24lt%22:1695308836%7D,%22end_time%22:%7B%22%24gt%22:1695308836%7D,%220%22:%7B%22%24or%22:%7B%22is_cancelled%22:%7B%22%24neq%22:1%7D,%22deletion_time%22:%7B%22%24gt%22:1695308836%7D%7D%7D%7D%5D%7D',
                forced_check:
                  '/centreon/api/latest/monitoring/hosts/14/services/19/check',
                performance_graph: null,
                status_graph:
                  '/centreon/api/latest/monitoring/hosts/14/services/19/metrics/status',
                timeline:
                  '/centreon/api/latest/monitoring/hosts/14/services/19/timeline'
              },
              externals: {
                action_url: '',
                notes: {
                  label: '',
                  url: ''
                }
              },
              uris: {
                configuration: '/centreon/main.php?p=60201&o=c&service_id=19',
                logs: '/centreon/main.php?p=20301&svc=14_19',
                reporting:
                  '/centreon/main.php?p=30702&period=yesterday&start=&end=&host_id=14&item=19'
              }
            },
            monitoring_server_name: 'Central',
            parent: {
              id: 14
            },
            performance_data: null,
            resource_name: 'Disk-/',
            severity: null,
            short_type: 's',
            status: {
              code: 3,
              name: 'UNKNOWN',
              severity_code: 3
            },
            tries: '3/3 (H)',
            type: 'service',
            uuid: 'h14-s19'
          },
          {
            alias: null,
            duration: '10m 55s',
            fqdn: null,
            has_active_checks_enabled: true,
            has_passive_checks_enabled: false,
            icon: null,
            id: 24,
            information: '(Execute command failed)',
            is_acknowledged: false,
            is_in_downtime: false,
            is_notification_enabled: false,
            last_check: '3m 55s',
            last_status_change: '2023-09-21T16:56:21+02:00',
            links: {
              endpoints: {
                acknowledgement:
                  '/centreon/api/latest/monitoring/hosts/14/services/24/acknowledgements?limit=1',
                check:
                  '/centreon/api/latest/monitoring/hosts/14/services/24/check',
                details:
                  '/centreon/api/latest/monitoring/resources/hosts/14/services/24',
                downtime:
                  '/centreon/api/latest/monitoring/hosts/14/services/24/downtimes?search=%7B%22%24and%22:%5B%7B%22start_time%22:%7B%22%24lt%22:1695308836%7D,%22end_time%22:%7B%22%24gt%22:1695308836%7D,%220%22:%7B%22%24or%22:%7B%22is_cancelled%22:%7B%22%24neq%22:1%7D,%22deletion_time%22:%7B%22%24gt%22:1695308836%7D%7D%7D%7D%5D%7D',
                forced_check:
                  '/centreon/api/latest/monitoring/hosts/14/services/24/check',
                performance_graph: null,
                status_graph:
                  '/centreon/api/latest/monitoring/hosts/14/services/24/metrics/status',
                timeline:
                  '/centreon/api/latest/monitoring/hosts/14/services/24/timeline'
              },
              externals: {
                action_url: '',
                notes: {
                  label: '',
                  url: ''
                }
              },
              uris: {
                configuration: '/centreon/main.php?p=60201&o=c&service_id=24',
                logs: '/centreon/main.php?p=20301&svc=14_24',
                reporting:
                  '/centreon/main.php?p=30702&period=yesterday&start=&end=&host_id=14&item=24'
              }
            },
            monitoring_server_name: 'Central',
            parent: {
              id: 14
            },
            performance_data: null,
            resource_name: 'Load',
            severity: null,
            short_type: 's',
            status: {
              code: 3,
              name: 'UNKNOWN',
              severity_code: 3
            },
            tries: '3/3 (H)',
            type: 'service',
            uuid: 'h14-s24'
          },
          {
            alias: null,
            duration: '12m 11s',
            fqdn: null,
            has_active_checks_enabled: true,
            has_passive_checks_enabled: false,
            icon: null,
            id: 25,
            information: '(Execute command failed)',
            is_acknowledged: true,
            is_in_downtime: false,
            is_notification_enabled: false,
            last_check: '1m 49s',
            last_status_change: '2023-09-21T16:55:05+02:00',
            links: {
              endpoints: {
                acknowledgement:
                  '/centreon/api/latest/monitoring/hosts/14/services/25/acknowledgements?limit=1',
                check:
                  '/centreon/api/latest/monitoring/hosts/14/services/25/check',
                details:
                  '/centreon/api/latest/monitoring/resources/hosts/14/services/25',
                downtime:
                  '/centreon/api/latest/monitoring/hosts/14/services/25/downtimes?search=%7B%22%24and%22:%5B%7B%22start_time%22:%7B%22%24lt%22:1695308836%7D,%22end_time%22:%7B%22%24gt%22:1695308836%7D,%220%22:%7B%22%24or%22:%7B%22is_cancelled%22:%7B%22%24neq%22:1%7D,%22deletion_time%22:%7B%22%24gt%22:1695308836%7D%7D%7D%7D%5D%7D',
                forced_check:
                  '/centreon/api/latest/monitoring/hosts/14/services/25/check',
                performance_graph: null,
                status_graph:
                  '/centreon/api/latest/monitoring/hosts/14/services/25/metrics/status',
                timeline:
                  '/centreon/api/latest/monitoring/hosts/14/services/25/timeline'
              },
              externals: {
                action_url: '',
                notes: {
                  label: '',
                  url: ''
                }
              },
              uris: {
                configuration: '/centreon/main.php?p=60201&o=c&service_id=25',
                logs: '/centreon/main.php?p=20301&svc=14_25',
                reporting:
                  '/centreon/main.php?p=30702&period=yesterday&start=&end=&host_id=14&item=25'
              }
            },
            monitoring_server_name: 'Central',
            parent: {
              id: 14
            },
            performance_data: null,
            resource_name: 'Memory',
            severity: null,
            short_type: 's',
            status: {
              code: 3,
              name: 'UNKNOWN',
              severity_code: 3
            },
            tries: '3/3 (H)',
            type: 'service',
            uuid: 'h14-s25'
          },
          {
            alias: null,
            duration: '13m 26s',
            fqdn: null,
            has_active_checks_enabled: true,
            has_passive_checks_enabled: false,
            icon: null,
            id: 26,
            information: 'OK - 127.0.0.1 rta 0.031ms lost 0%',
            is_acknowledged: false,
            is_in_downtime: false,
            is_notification_enabled: false,
            last_check: '3m 26s',
            last_status_change: '2023-09-21T16:53:50+02:00',
            links: {
              endpoints: {
                acknowledgement:
                  '/centreon/api/latest/monitoring/hosts/14/services/26/acknowledgements?limit=1',
                check:
                  '/centreon/api/latest/monitoring/hosts/14/services/26/check',
                details:
                  '/centreon/api/latest/monitoring/resources/hosts/14/services/26',
                downtime:
                  '/centreon/api/latest/monitoring/hosts/14/services/26/downtimes?search=%7B%22%24and%22:%5B%7B%22start_time%22:%7B%22%24lt%22:1695308836%7D,%22end_time%22:%7B%22%24gt%22:1695308836%7D,%220%22:%7B%22%24or%22:%7B%22is_cancelled%22:%7B%22%24neq%22:1%7D,%22deletion_time%22:%7B%22%24gt%22:1695308836%7D%7D%7D%7D%5D%7D',
                forced_check:
                  '/centreon/api/latest/monitoring/hosts/14/services/26/check',
                performance_graph:
                  '/centreon/api/latest/monitoring/hosts/14/services/26/metrics/performance',
                status_graph:
                  '/centreon/api/latest/monitoring/hosts/14/services/26/metrics/status',
                timeline:
                  '/centreon/api/latest/monitoring/hosts/14/services/26/timeline'
              },
              externals: {
                action_url: '',
                notes: {
                  label: '',
                  url: ''
                }
              },
              uris: {
                configuration: '/centreon/main.php?p=60201&o=c&service_id=26',
                logs: '/centreon/main.php?p=20301&svc=14_26',
                reporting:
                  '/centreon/main.php?p=30702&period=yesterday&start=&end=&host_id=14&item=26'
              }
            },
            monitoring_server_name: 'Central',
            parent: {
              id: 14
            },
            performance_data: null,
            resource_name: 'Ping',
            severity: null,
            short_type: 's',
            status: {
              code: 0,
              name: 'OK',
              severity_code: 5
            },
            tries: '1/3 (H)',
            type: 'service',
            uuid: 'h14-s26'
          }
        ],
        status_count: {
          critical: 0,
          ok: 1,
          pending: 0,
          unknown: 3,
          warning: 0
        },
        total: 4
      },
      duration: '13m 26s',
      fqdn: '127.0.0.1',
      has_active_checks_enabled: true,
      has_passive_checks_enabled: false,
      icon: null,
      id: 14,
      information: 'OK - 127.0.0.1 rta 0.049ms lost 0%',
      is_acknowledged: false,
      is_in_downtime: false,
      is_notification_enabled: false,
      last_check: '4m 41s',
      last_status_change: '2023-09-21T16:53:50+02:00',
      links: {
        endpoints: {
          acknowledgement:
            '/centreon/api/latest/monitoring/hosts/14/acknowledgements?limit=1',
          check: '/centreon/api/latest/monitoring/hosts/14/check',
          details: '/centreon/api/latest/monitoring/resources/hosts/14',
          downtime:
            '/centreon/api/latest/monitoring/hosts/14/downtimes?search=%7B%22%24and%22:%5B%7B%22start_time%22:%7B%22%24lt%22:1695308836%7D,%22end_time%22:%7B%22%24gt%22:1695308836%7D,%220%22:%7B%22%24or%22:%7B%22is_cancelled%22:%7B%22%24neq%22:1%7D,%22deletion_time%22:%7B%22%24gt%22:1695308836%7D%7D%7D%7D%5D%7D',
          forced_check: '/centreon/api/latest/monitoring/hosts/14/check',
          performance_graph: null,
          status_graph: null,
          timeline: '/centreon/api/latest/monitoring/hosts/14/timeline'
        },
        externals: {
          action_url: '',
          notes: {
            label: '',
            url: ''
          }
        },
        uris: {
          configuration: '/centreon/main.php?p=60101&o=c&host_id=14',
          logs: '/centreon/main.php?p=20301&h=14',
          reporting: '/centreon/main.php?p=307&host=14'
        }
      },
      monitoring_server_name: 'Central',
      name: 'Centreon-Server',
      parent: null,
      performance_data: null,
      severity: null,
      short_type: 'h',
      status: {
        code: 0,
        name: 'UP',
        severity_code: 5
      },
      tries: '1/5 (H)',
      type: 'host',
      uuid: 'h14'
    },
    {
      alias: 'Monitoring Server',
      children: {
        resources: [
          {
            alias: null,
            duration: null,
            fqdn: null,
            has_active_checks_enabled: true,
            has_passive_checks_enabled: false,
            icon: null,
            id: 27,
            information: '',
            is_acknowledged: false,
            is_in_downtime: false,
            is_notification_enabled: false,
            last_check: null,
            last_status_change: null,
            links: {
              endpoints: {
                acknowledgement:
                  '/centreon/api/latest/monitoring/hosts/15/services/27/acknowledgements?limit=1',
                check:
                  '/centreon/api/latest/monitoring/hosts/15/services/27/check',
                details:
                  '/centreon/api/latest/monitoring/resources/hosts/15/services/27',
                downtime:
                  '/centreon/api/latest/monitoring/hosts/15/services/27/downtimes?search=%7B%22%24and%22:%5B%7B%22start_time%22:%7B%22%24lt%22:1695308836%7D,%22end_time%22:%7B%22%24gt%22:1695308836%7D,%220%22:%7B%22%24or%22:%7B%22is_cancelled%22:%7B%22%24neq%22:1%7D,%22deletion_time%22:%7B%22%24gt%22:1695308836%7D%7D%7D%7D%5D%7D',
                forced_check:
                  '/centreon/api/latest/monitoring/hosts/15/services/27/check',
                performance_graph: null,
                status_graph:
                  '/centreon/api/latest/monitoring/hosts/15/services/27/metrics/status',
                timeline:
                  '/centreon/api/latest/monitoring/hosts/15/services/27/timeline'
              },
              externals: {
                action_url: '',
                notes: {
                  label: '',
                  url: ''
                }
              },
              uris: {
                configuration: '/centreon/main.php?p=60201&o=c&service_id=27',
                logs: '/centreon/main.php?p=20301&svc=15_27',
                reporting:
                  '/centreon/main.php?p=30702&period=yesterday&start=&end=&host_id=15&item=27'
              }
            },
            monitoring_server_name: 'Central',
            parent: {
              id: 15
            },
            performance_data: null,
            resource_name: 'Disk-/',
            severity: null,
            short_type: 's',
            status: {
              code: 4,
              name: 'PENDING',
              severity_code: 4
            },
            tries: '1/3 (H)',
            type: 'service',
            uuid: 'h15-s27'
          },
          {
            alias: null,
            duration: null,
            fqdn: null,
            has_active_checks_enabled: true,
            has_passive_checks_enabled: false,
            icon: null,
            id: 32,
            information: '',
            is_acknowledged: false,
            is_in_downtime: false,
            is_notification_enabled: false,
            last_check: null,
            last_status_change: null,
            links: {
              endpoints: {
                acknowledgement:
                  '/centreon/api/latest/monitoring/hosts/15/services/32/acknowledgements?limit=1',
                check:
                  '/centreon/api/latest/monitoring/hosts/15/services/32/check',
                details:
                  '/centreon/api/latest/monitoring/resources/hosts/15/services/32',
                downtime:
                  '/centreon/api/latest/monitoring/hosts/15/services/32/downtimes?search=%7B%22%24and%22:%5B%7B%22start_time%22:%7B%22%24lt%22:1695308836%7D,%22end_time%22:%7B%22%24gt%22:1695308836%7D,%220%22:%7B%22%24or%22:%7B%22is_cancelled%22:%7B%22%24neq%22:1%7D,%22deletion_time%22:%7B%22%24gt%22:1695308836%7D%7D%7D%7D%5D%7D',
                forced_check:
                  '/centreon/api/latest/monitoring/hosts/15/services/32/check',
                performance_graph: null,
                status_graph:
                  '/centreon/api/latest/monitoring/hosts/15/services/32/metrics/status',
                timeline:
                  '/centreon/api/latest/monitoring/hosts/15/services/32/timeline'
              },
              externals: {
                action_url: '',
                notes: {
                  label: '',
                  url: ''
                }
              },
              uris: {
                configuration: '/centreon/main.php?p=60201&o=c&service_id=32',
                logs: '/centreon/main.php?p=20301&svc=15_32',
                reporting:
                  '/centreon/main.php?p=30702&period=yesterday&start=&end=&host_id=15&item=32'
              }
            },
            monitoring_server_name: 'Central',
            parent: {
              id: 15
            },
            performance_data: null,
            resource_name: 'Load',
            severity: null,
            short_type: 's',
            status: {
              code: 4,
              name: 'PENDING',
              severity_code: 4
            },
            tries: '1/3 (H)',
            type: 'service',
            uuid: 'h15-s32'
          },
          {
            alias: null,
            duration: null,
            fqdn: null,
            has_active_checks_enabled: true,
            has_passive_checks_enabled: false,
            icon: null,
            id: 33,
            information: '',
            is_acknowledged: false,
            is_in_downtime: false,
            is_notification_enabled: false,
            last_check: null,
            last_status_change: null,
            links: {
              endpoints: {
                acknowledgement:
                  '/centreon/api/latest/monitoring/hosts/15/services/33/acknowledgements?limit=1',
                check:
                  '/centreon/api/latest/monitoring/hosts/15/services/33/check',
                details:
                  '/centreon/api/latest/monitoring/resources/hosts/15/services/33',
                downtime:
                  '/centreon/api/latest/monitoring/hosts/15/services/33/downtimes?search=%7B%22%24and%22:%5B%7B%22start_time%22:%7B%22%24lt%22:1695308836%7D,%22end_time%22:%7B%22%24gt%22:1695308836%7D,%220%22:%7B%22%24or%22:%7B%22is_cancelled%22:%7B%22%24neq%22:1%7D,%22deletion_time%22:%7B%22%24gt%22:1695308836%7D%7D%7D%7D%5D%7D',
                forced_check:
                  '/centreon/api/latest/monitoring/hosts/15/services/33/check',
                performance_graph: null,
                status_graph:
                  '/centreon/api/latest/monitoring/hosts/15/services/33/metrics/status',
                timeline:
                  '/centreon/api/latest/monitoring/hosts/15/services/33/timeline'
              },
              externals: {
                action_url: '',
                notes: {
                  label: '',
                  url: ''
                }
              },
              uris: {
                configuration: '/centreon/main.php?p=60201&o=c&service_id=33',
                logs: '/centreon/main.php?p=20301&svc=15_33',
                reporting:
                  '/centreon/main.php?p=30702&period=yesterday&start=&end=&host_id=15&item=33'
              }
            },
            monitoring_server_name: 'Central',
            parent: {
              id: 15
            },
            performance_data: null,
            resource_name: 'Memory',
            severity: null,
            short_type: 's',
            status: {
              code: 4,
              name: 'PENDING',
              severity_code: 4
            },
            tries: '1/3 (H)',
            type: 'service',
            uuid: 'h15-s33'
          },
          {
            alias: null,
            duration: '1m 3s',
            fqdn: null,
            has_active_checks_enabled: true,
            has_passive_checks_enabled: false,
            icon: null,
            id: 34,
            information: 'OK - 127.0.0.1 rta 0.023ms lost 0%',
            is_acknowledged: false,
            is_in_downtime: false,
            is_notification_enabled: false,
            last_check: '1m 3s',
            last_status_change: '2023-09-21T17:06:13+02:00',
            links: {
              endpoints: {
                acknowledgement:
                  '/centreon/api/latest/monitoring/hosts/15/services/34/acknowledgements?limit=1',
                check:
                  '/centreon/api/latest/monitoring/hosts/15/services/34/check',
                details:
                  '/centreon/api/latest/monitoring/resources/hosts/15/services/34',
                downtime:
                  '/centreon/api/latest/monitoring/hosts/15/services/34/downtimes?search=%7B%22%24and%22:%5B%7B%22start_time%22:%7B%22%24lt%22:1695308836%7D,%22end_time%22:%7B%22%24gt%22:1695308836%7D,%220%22:%7B%22%24or%22:%7B%22is_cancelled%22:%7B%22%24neq%22:1%7D,%22deletion_time%22:%7B%22%24gt%22:1695308836%7D%7D%7D%7D%5D%7D',
                forced_check:
                  '/centreon/api/latest/monitoring/hosts/15/services/34/check',
                performance_graph:
                  '/centreon/api/latest/monitoring/hosts/15/services/34/metrics/performance',
                status_graph:
                  '/centreon/api/latest/monitoring/hosts/15/services/34/metrics/status',
                timeline:
                  '/centreon/api/latest/monitoring/hosts/15/services/34/timeline'
              },
              externals: {
                action_url: '',
                notes: {
                  label: '',
                  url: ''
                }
              },
              uris: {
                configuration: '/centreon/main.php?p=60201&o=c&service_id=34',
                logs: '/centreon/main.php?p=20301&svc=15_34',
                reporting:
                  '/centreon/main.php?p=30702&period=yesterday&start=&end=&host_id=15&item=34'
              }
            },
            monitoring_server_name: 'Central',
            parent: {
              id: 15
            },
            performance_data: null,
            resource_name: 'Ping',
            severity: null,
            short_type: 's',
            status: {
              code: 0,
              name: 'OK',
              severity_code: 5
            },
            tries: '1/3 (H)',
            type: 'service',
            uuid: 'h15-s34'
          }
        ],
        status_count: {
          critical: 0,
          ok: 1,
          pending: 3,
          unknown: 0,
          warning: 0
        },
        total: 4
      },
      duration: null,
      fqdn: '127.0.0.1',
      has_active_checks_enabled: true,
      has_passive_checks_enabled: false,
      icon: null,
      id: 15,
      information: 'OK - 127.0.0.1 rta 0.057ms lost 0%',
      is_acknowledged: false,
      is_in_downtime: false,
      is_notification_enabled: false,
      last_check: '2m 18s',
      last_status_change: null,
      links: {
        endpoints: {
          acknowledgement:
            '/centreon/api/latest/monitoring/hosts/15/acknowledgements?limit=1',
          check: '/centreon/api/latest/monitoring/hosts/15/check',
          details: '/centreon/api/latest/monitoring/resources/hosts/15',
          downtime:
            '/centreon/api/latest/monitoring/hosts/15/downtimes?search=%7B%22%24and%22:%5B%7B%22start_time%22:%7B%22%24lt%22:1695308836%7D,%22end_time%22:%7B%22%24gt%22:1695308836%7D,%220%22:%7B%22%24or%22:%7B%22is_cancelled%22:%7B%22%24neq%22:1%7D,%22deletion_time%22:%7B%22%24gt%22:1695308836%7D%7D%7D%7D%5D%7D',
          forced_check: '/centreon/api/latest/monitoring/hosts/15/check',
          performance_graph: null,
          status_graph: null,
          timeline: '/centreon/api/latest/monitoring/hosts/15/timeline'
        },
        externals: {
          action_url: '',
          notes: {
            label: '',
            url: ''
          }
        },
        uris: {
          configuration: '/centreon/main.php?p=60101&o=c&host_id=15',
          logs: '/centreon/main.php?p=20301&h=15',
          reporting: '/centreon/main.php?p=307&host=15'
        }
      },
      monitoring_server_name: 'Central',
      name: 'Centreon-Server_1',
      parent: null,
      performance_data: null,
      severity: null,
      short_type: 'h',
      status: {
        code: 0,
        name: 'UP',
        severity_code: 5
      },
      tries: '1/5 (H)',
      type: 'host',
      uuid: 'h15'
    }
  ]
};
