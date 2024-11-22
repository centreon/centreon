import { Resource } from '../../../models';
import { DisplayType, PanelOptions } from '../models';

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

export const options: PanelOptions = {
  displayLegend: true,
  displayType: DisplayType.Donut,
  displayValues: true,
  refreshInterval: 'manual',
  refreshIntervalCustom: 30,
  resourceTypes: ['host', 'service'],
  unit: 'percentage'
};

export const serviceStatus = [
  { count: 39, status: 'critical' },
  { count: 30, status: 'warning' },
  { count: 99, status: 'unknown' },
  { count: 99, status: 'pending' },
  { count: 411, status: 'ok' }
];

export const hostStatus = [
  { count: 42, status: 'down' },
  { count: 18, status: 'unreachable' },
  { count: 29, status: 'pending' },
  { count: 123, status: 'up' }
];

export const totalHosts = 212;
export const totalServices = 678;
