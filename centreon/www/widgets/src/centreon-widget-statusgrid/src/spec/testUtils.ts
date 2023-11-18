import { Resource } from '../../../models';
import { PanelOptions } from '../models';

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
  states: ['in_downtime'],
  statuses: ['up', 'down'],
  tiles: 20
};

export const serviceOptions: PanelOptions = {
  refreshInterval: 'manual',
  refreshIntervalCustom: 30,
  resourceType: 'service',
  sortBy: 'status',
  states: ['acknowledged'],
  statuses: ['ok', 'critical'],
  tiles: 20
};

export const seeMoreOptions: PanelOptions = {
  refreshInterval: 'manual',
  refreshIntervalCustom: 30,
  resourceType: 'service',
  sortBy: 'status',
  states: ['acknowledged'],
  statuses: ['ok', 'critical'],
  tiles: 1
};

export const services = [
  {
    color: 'rgb(136, 185, 34)',
    eq: 0,
    name: 'Ping',
    status: 'ok'
  },
  {
    color: 'rgb(240, 233, 248)',
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
    color: 'rgb(245, 241, 233)',
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
