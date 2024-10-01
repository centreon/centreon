import { Resource, SeverityStatus } from '../../../models';
import { PanelOptions } from '../StatusGridStandard/models';

export const noResources = [];
export const resources: Array<Resource> = [
  {
    resourceType: 'host',
    resources: [
      {
        id: 1,
        name: 'Host'
      }
    ]
  },
  {
    resourceType: 'host-group',
    resources: [
      {
        id: 1,
        name: 'HG1'
      },
      {
        id: 2,
        name: 'HG2'
      }
    ]
  }
];

export const hostOptions: PanelOptions = {
  refreshInterval: 'manual',
  refreshIntervalCustom: 30,
  resourceType: 'host',
  sortBy: 'status',
  statuses: [SeverityStatus.Success, SeverityStatus.Problem],
  tiles: 20,
  viewMode: 'standard'
};

export const serviceOptions: PanelOptions = {
  refreshInterval: 'manual',
  refreshIntervalCustom: 30,
  resourceType: 'service',
  sortBy: 'status',
  statuses: [SeverityStatus.Success, SeverityStatus.Problem],
  tiles: 20,
  viewMode: 'standard'
};

export const seeMoreOptions: PanelOptions = {
  refreshInterval: 'manual',
  refreshIntervalCustom: 30,
  resourceType: 'service',
  sortBy: 'status',
  statuses: [SeverityStatus.Success, SeverityStatus.Problem],
  tiles: 1,
  viewMode: 'standard'
};

export const condensedOptions: PanelOptions = {
  refreshInterval: 'manual',
  refreshIntervalCustom: 30,
  resourceType: 'service',
  sortBy: 'status',
  statuses: [
    SeverityStatus.Success,
    SeverityStatus.Problem,
    SeverityStatus.Pending
  ],
  tiles: 20,
  viewMode: 'condensed'
};

export const services = [
  {
    color: 'rgb(136, 185, 34)',
    eq: 0,
    name: 'Ping',
    status: 'ok'
  },
  {
    color: 'rgb(227, 227, 227)',
    eq: 0,
    name: 'Disk-/',
    status: 'unknown'
  },
  {
    color: 'rgb(227, 227, 227)',
    eq: 1,
    name: 'Load',
    status: 'unknown'
  },
  {
    color: 'rgb(227, 227, 227)',
    eq: 2,
    name: 'Memory',
    status: 'unknown'
  },
  {
    color: 'rgb(253, 155, 39)',
    eq: 0,
    name: 'Passive',
    status: 'warning'
  },
  {
    color: 'rgb(255, 102, 102)',
    eq: 0,
    name: 'Centreon_Pass',
    status: 'critical'
  }
];

export const bvResources: Array<Resource> = [
  {
    resourceType: 'business-view',
    resources: [
      {
        id: 1,
        name: 'bv1'
      }
    ]
  }
];

export const baResources: Array<Resource> = [
  {
    resourceType: 'business-view',
    resources: [
      {
        id: 1,
        name: 'bv1'
      }
    ]
  },
  {
    resourceType: 'business-activity',
    resources: [
      {
        id: 1,
        name: 'ba1'
      }
    ]
  }
];

export const baOptions: PanelOptions = {
  refreshInterval: 'manual',
  refreshIntervalCustom: 30,
  sortBy: 'status',
  statuses: [SeverityStatus.Success, SeverityStatus.Problem],
  tiles: 20,
  viewMode: 'standard'
};

export const baCondensedOptions: PanelOptions = {
  refreshInterval: 'manual',
  refreshIntervalCustom: 30,
  sortBy: 'status',
  statuses: [
    SeverityStatus.Success,
    SeverityStatus.Problem,
    SeverityStatus.Pending
  ],
  tiles: 20,
  viewMode: 'condensed'
};

export const linkToAllRessource =
  '/monitoring/resources?filter=%7B%22criterias%22%3A%5B%7B%22name%22%3A%22resource_types%22%2C%22value%22%3A%5B%7B%22name%22%3A%22undefinedundefined%22%7D%5D%7D%2C%7B%22name%22%3A%22statuses%22%2C%22value%22%3A%5B%7B%22id%22%3A%22OK%22%2C%22name%22%3A%22Ok%22%7D%2C%7B%22id%22%3A%22UP%22%2C%22name%22%3A%22Up%22%7D%2C%7B%22id%22%3A%22DOWN%22%2C%22name%22%3A%22Down%22%7D%2C%7B%22id%22%3A%22CRITICAL%22%2C%22name%22%3A%22Critical%22%7D%5D%7D%2C%7B%22name%22%3A%22states%22%2C%22value%22%3A%5B%5D%7D%2C%7B%22name%22%3A%22parent_name%22%2C%22value%22%3A%5B%7B%22id%22%3A%22%5C%5CbHost%5C%5Cb%22%2C%22name%22%3A%22Host%22%7D%5D%7D%2C%7B%22name%22%3A%22host_group%22%2C%22value%22%3A%5B%7B%22id%22%3A%22HG1%22%2C%22name%22%3A%22HG1%22%7D%2C%7B%22id%22%3A%22HG2%22%2C%22name%22%3A%22HG2%22%7D%5D%7D%2C%7B%22name%22%3A%22search%22%2C%22value%22%3A%22%22%7D%5D%7D&fromTopCounter=true';
export const linkToResourcePing =
  '/monitoring/resources?details=%7B%22id%22%3A26%2C%22resourcesDetailsEndpoint%22%3A%22%2Fapi%2Flatest%2Fmonitoring%2Fresources%2Fhosts%2F14%2Fservices%2F26%22%2C%22selectedTimePeriodId%22%3A%22last_24_h%22%2C%22tab%22%3A%22details%22%2C%22tabParameters%22%3A%7B%7D%2C%22uuid%22%3A%22h14-s26%22%7D&filter=%7B%22criterias%22%3A%5B%7B%22name%22%3A%22resource_types%22%2C%22value%22%3A%5B%7B%22id%22%3A%22service%22%2C%22name%22%3A%22Service%22%7D%5D%7D%2C%7B%22name%22%3A%22statuses%22%2C%22value%22%3A%5B%7B%22id%22%3A%22OK%22%2C%22name%22%3A%22Ok%22%7D%2C%7B%22id%22%3A%22UP%22%2C%22name%22%3A%22Up%22%7D%2C%7B%22id%22%3A%22DOWN%22%2C%22name%22%3A%22Down%22%7D%2C%7B%22id%22%3A%22CRITICAL%22%2C%22name%22%3A%22Critical%22%7D%5D%7D%2C%7B%22name%22%3A%22states%22%2C%22value%22%3A%5B%5D%7D%2C%7B%22name%22%3A%22parent_name%22%2C%22value%22%3A%5B%7B%22id%22%3A%22%5C%5CbHost%5C%5Cb%22%2C%22name%22%3A%22Host%22%7D%5D%7D%2C%7B%22name%22%3A%22host_group%22%2C%22value%22%3A%5B%7B%22id%22%3A%22HG1%22%2C%22name%22%3A%22HG1%22%7D%2C%7B%22id%22%3A%22HG2%22%2C%22name%22%3A%22HG2%22%7D%5D%7D%2C%7B%22name%22%3A%22search%22%2C%22value%22%3A%22%22%7D%5D%7D&fromTopCounter=true';
export const linkToResourceCentreonPass =
  '/monitoring/resources?details=%7B%22id%22%3A28%2C%22resourcesDetailsEndpoint%22%3A%22%2Fapi%2Flatest%2Fmonitoring%2Fresources%2Fhosts%2F14%2Fservices%2F28%22%2C%22selectedTimePeriodId%22%3A%22last_24_h%22%2C%22tab%22%3A%22details%22%2C%22tabParameters%22%3A%7B%7D%2C%22uuid%22%3A%22h14-s28%22%7D&filter=%7B%22criterias%22%3A%5B%7B%22name%22%3A%22resource_types%22%2C%22value%22%3A%5B%7B%22id%22%3A%22service%22%2C%22name%22%3A%22Service%22%7D%5D%7D%2C%7B%22name%22%3A%22statuses%22%2C%22value%22%3A%5B%7B%22id%22%3A%22OK%22%2C%22name%22%3A%22Ok%22%7D%2C%7B%22id%22%3A%22UP%22%2C%22name%22%3A%22Up%22%7D%2C%7B%22id%22%3A%22DOWN%22%2C%22name%22%3A%22Down%22%7D%2C%7B%22id%22%3A%22CRITICAL%22%2C%22name%22%3A%22Critical%22%7D%5D%7D%2C%7B%22name%22%3A%22states%22%2C%22value%22%3A%5B%5D%7D%2C%7B%22name%22%3A%22parent_name%22%2C%22value%22%3A%5B%7B%22id%22%3A%22%5C%5CbHost%5C%5Cb%22%2C%22name%22%3A%22Host%22%7D%5D%7D%2C%7B%22name%22%3A%22host_group%22%2C%22value%22%3A%5B%7B%22id%22%3A%22HG1%22%2C%22name%22%3A%22HG1%22%7D%2C%7B%22id%22%3A%22HG2%22%2C%22name%22%3A%22HG2%22%7D%5D%7D%2C%7B%22name%22%3A%22search%22%2C%22value%22%3A%22%22%7D%5D%7D&fromTopCounter=true';
export const linkToResourceCentreonServer =
  '/monitoring/resources?details=%7B%22id%22%3A14%2C%22resourcesDetailsEndpoint%22%3A%22%2Fapi%2Flatest%2Fmonitoring%2Fresources%2Fhosts%2F14%22%2C%22selectedTimePeriodId%22%3A%22last_24_h%22%2C%22tab%22%3A%22details%22%2C%22tabParameters%22%3A%7B%7D%2C%22uuid%22%3A%22h14%22%7D&filter=%7B%22criterias%22%3A%5B%7B%22name%22%3A%22resource_types%22%2C%22value%22%3A%5B%7B%22id%22%3A%22host%22%2C%22name%22%3A%22Host%22%7D%5D%7D%2C%7B%22name%22%3A%22statuses%22%2C%22value%22%3A%5B%7B%22id%22%3A%22OK%22%2C%22name%22%3A%22Ok%22%7D%2C%7B%22id%22%3A%22UP%22%2C%22name%22%3A%22Up%22%7D%2C%7B%22id%22%3A%22DOWN%22%2C%22name%22%3A%22Down%22%7D%2C%7B%22id%22%3A%22CRITICAL%22%2C%22name%22%3A%22Critical%22%7D%5D%7D%2C%7B%22name%22%3A%22states%22%2C%22value%22%3A%5B%5D%7D%2C%7B%22name%22%3A%22parent_name%22%2C%22value%22%3A%5B%7B%22id%22%3A%22%5C%5CbHost%5C%5Cb%22%2C%22name%22%3A%22Host%22%7D%5D%7D%2C%7B%22name%22%3A%22host_group%22%2C%22value%22%3A%5B%7B%22id%22%3A%22HG1%22%2C%22name%22%3A%22HG1%22%7D%2C%7B%22id%22%3A%22HG2%22%2C%22name%22%3A%22HG2%22%7D%5D%7D%2C%7B%22name%22%3A%22search%22%2C%22value%22%3A%22%22%7D%5D%7D&fromTopCounter=true';
