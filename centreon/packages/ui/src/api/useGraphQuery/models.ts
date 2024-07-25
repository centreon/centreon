import { SelectEntry } from '../..';

export interface Resource {
  resourceType: string;
  resources: Array<SelectEntry>;
}

export enum WidgetResourceType {
  host = 'host',
  hostCategory = 'host-category',
  hostGroup = 'host-group',
  metaService = 'meta-service',
  service = 'service',
  serviceCategory = 'service-category',
  serviceGroup = 'service-group'
}

export interface Metric {
  excludedMetrics: Array<number>;
  name: string;
}
