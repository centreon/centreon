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
  metrics: Array<SelectEntry>;
  serviceId: number;
}

export interface NamedEntity {
  id: number;
  name: string;
}

export interface ServiceMetric {
  metrics: Array<NamedEntity>;
  resourceName: string;
  serviceId: number;
}
