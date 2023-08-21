import { ReactNode } from 'react';

import { SelectEntry } from '@centreon/ui';

import { PanelConfiguration, WidgetOptions } from '../models';

export interface Widget {
  data: object | null;
  id: string | null;
  moduleName: string | null;
  options: WidgetOptions;
  panelConfiguration: PanelConfiguration | null;
}

export interface WidgetPropertyProps {
  className?: string;
  disabled?: boolean;
  disabledCondition?: (values: Widget) => boolean;
  endAdornment?: ReactNode;
  label: string;
  propertyName: string;
  required?: boolean;
  text?: {
    multiline?: boolean;
    size?: string;
    type?: string;
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
  criticalThreshold: number | null;
  unit: string;
  warningThreshold: number | null;
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

export enum RadioOptions {
  custom = 'custom',
  default = 'default',
  manual = 'manual'
}
