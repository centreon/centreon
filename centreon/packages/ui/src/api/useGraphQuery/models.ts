import { SelectEntry } from '../..';

export interface Resource {
  resourceType: string;
  resources: Array<SelectEntry>;
}

export enum WidgetResourceType {
  host = 'host',
  hostCategory = 'host-category',
  hostGroup = 'host-group',
  service = 'service',
  metaService = 'meta-service',
  serviceCategory = 'service-category',
  serviceGroup = 'service-group'
}

export interface Metric {
  excludedMetrics: Array<number>;
  name: string;
}
