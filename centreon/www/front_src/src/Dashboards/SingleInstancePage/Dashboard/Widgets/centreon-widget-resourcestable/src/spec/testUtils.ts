import { Resource, SortOrder } from '../../../models';
import { DisplayType } from '../Listing/models';
import { PanelOptions } from '../models';

export const resources: Array<Resource> = [
  {
    resources: [
      {
        id: 1,
        name: 'Host'
      }
    ],
    resourceType: 'host'
  },
  {
    resources: [
      {
        id: 1,
        name: 'HG1'
      },
      {
        id: 2,
        name: 'HG2'
      }
    ],
    resourceType: 'host-group'
  }
];

export const metaServiceResources: Array<Resource> = [
  {
    resources: [
      {
        id: 1,
        name: 'Meta service'
      }
    ],
    resourceType: 'meta-service'
  }
];

export const selectedColumnIds = [
  'status',
  'resource',
  'parent_resource',
  'state',
  'information'
];

export const columnsForViewByAll = [
  'Status',
  'Resource',
  'Parent',
  'State',
  'Information'
];

export const columnsForViewByHost = [
  'Status',
  'Host',
  'Services',
  'State',
  'Information'
];

export const columnsForViewByService = [
  'Status',
  'Service',
  'Host',
  'State',
  'Information'
];

export const options: PanelOptions = {
  displayResources: 'all',
  displayType: DisplayType.All,
  hostSeverities: [],
  isDownHostHidden: false,
  isOpenTicketEnabled: false,
  isUnreachableHostHidden: false,
  limit: 40,
  refreshInterval: 'manual',
  refreshIntervalCustom: 30,
  selectedColumnIds,
  serviceSeverities: [],
  sortField: 'status',
  sortOrder: SortOrder.Desc,
  states: [],
  statuses: ['success', 'problem', 'undefined'],
  statusTypes: []
};

export const resourcesRegex: Array<Resource> = [
  {
    resources: '^Loa',
    resourceType: 'service'
  },
  {
    resources: [
      {
        id: 1,
        name: 'H1'
      }
    ],
    resourceType: 'host'
  }
];
