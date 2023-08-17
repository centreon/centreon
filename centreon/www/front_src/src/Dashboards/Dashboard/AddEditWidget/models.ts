import { SelectEntry } from '@centreon/ui';

import { PanelConfiguration } from '../models';

export interface Widget {
  data: object | null;
  id: string | null;
  moduleName: string | null;
  options: object;
  panelConfiguration: PanelConfiguration | null;
}

export interface WidgetPropertyProps {
  label: string;
  propertyName: string;
  required?: boolean;
  text?: {
    multiline?: boolean;
  };
}

export interface WidgetDataResource {
  resourceType: 'host-group' | 'host-category' | 'host' | 'service';
  resources: Array<SelectEntry>;
}
export interface WidgetDataMetric {
  id: number;
  metrics: Array<SelectEntry>;
}

export interface NamedEntity {
  id: number;
  name: string;
}

export interface Metric extends NamedEntity {
  unit: string;
}

export interface ServiceMetric extends NamedEntity {
  metrics: Array<Metric>;
}

export enum WidgetResourceType {
  host = 'host',
  hostCategory = 'host-category',
  hostGroup = 'host-group',
  service = 'service'
}
