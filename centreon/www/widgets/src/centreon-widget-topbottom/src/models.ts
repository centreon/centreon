import { SelectEntry } from '@centreon/ui';

import { Metric } from '../../models';

export interface Resource {
  criticalHighThreshold: number | null;
  criticalLowThreshold: number | null;
  currentValue: number | null;
  id: number;
  max: number | null;
  min: number | null;
  name: string;
  parentName: string;
  uuid: string | null;
  warningHighThreshold: number | null;
  warningLowThreshold: number | null;
}

export interface MetricsTop {
  name: string;
  resources: Array<Resource>;
  unit: string;
}

export interface WidgetDataResource {
  resourceType: 'host-group' | 'host-category' | 'host' | 'service';
  resources: Array<SelectEntry>;
}

export interface Data {
  metrics: Array<Metric>;
  resources: Array<WidgetDataResource>;
}

export type ValueFormat = 'human' | 'raw';

export interface TopBottomSettings {
  numberOfValues: number;
  order: 'top' | 'bottom';
  showLabels: boolean;
}
