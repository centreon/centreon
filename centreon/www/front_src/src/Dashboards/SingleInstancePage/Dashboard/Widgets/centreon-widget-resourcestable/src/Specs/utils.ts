import { SortOrder } from '../../../models';
import { DisplayType } from '../Listing/models';
import type { Data, PanelOptions } from '../models';

const getResource = (index: number): object => ({
  alias: null,
  duration: '13h 29m',
  fqdn: null,
  has_active_checks_enabled: true,
  has_passive_checks_enabled: false,
  host_id: 14,
  icon: null,
  id: index,
  information: 'OK',
  is_acknowledged: false,
  is_in_downtime: false,
  is_notification_enabled: false,
  last_check: '4m 20s',
  links: {
    endpoints: {},
    externals: {},
    uris: {}
  },
  monitoring_server_name: 'Central',
  name: `Service ${index}`,
  parent: {
    alias: 'Monitoring Server',
    fqdn: '127.0.0.1',
    id: 14,
    name: 'Centreon-Server',
    short_type: 'h',
    status: { code: 0, name: 'UP', severity_code: 5 },
    type: 'host',
    uuid: 'h14'
  },
  service_id: index,
  severity: null,
  short_type: 's',
  status: { code: 2, name: 'CRITICAL', severity_code: 1 },
  tries: '3/3 (H)',
  type: 'service',
  uuid: `h14-s${index}`
});

export const getListingResponse = (): object => ({
  meta: {
    limit: 10,
    page: 1,
    search: {},
    sort_by: {},
    total: 25
  },
  result: [getResource(1), getResource(2)]
});

export const emptyListingResponse = {
  meta: { limit: 10, page: 1, search: {}, sort_by: {}, total: 0 },
  result: []
};

export const selectedColumnIds = [
  'status',
  'resource',
  'parent_resource',
  'information'
];

// Every column of the widget in the "All" display, the selected ones first.
export const allColumnIds = [
  ...selectedColumnIds,
  'duration',
  'tries',
  'last_check',
  'severity',
  'notes_url',
  'action_url',
  'state',
  'alias',
  'parent_alias',
  'fqdn',
  'monitoring_server_name'
];

export const panelData: Data = {
  resources: [
    {
      resources: [{ id: 1, name: 'HG1' }],
      resourceType: 'host-group'
    },
    {
      resources: '^Serv',
      resourceType: 'service'
    }
  ]
} as Data;

export const panelOptions: PanelOptions = {
  displayResources: 'withoutTicket',
  displayType: DisplayType.All,
  enableHostTicketCreation: false,
  enableServiceTicketCreation: false,
  hostSeverities: [{ id: 1, name: 'Priority 1' }],
  isDownHostHidden: false,
  isExportEnabled: true,
  isOpenTicketEnabled: false,
  isUnreachableHostHidden: false,
  limit: 10,
  refreshInterval: 'manual',
  selectedColumnIds,
  serviceSeverities: [{ id: 2, name: 'Priority 2' }],
  sortField: 'status_severity_code',
  sortOrder: SortOrder.Desc,
  states: ['unhandled_problems'],
  statuses: ['problem', 'warning'],
  statusTypes: ['hard']
};
