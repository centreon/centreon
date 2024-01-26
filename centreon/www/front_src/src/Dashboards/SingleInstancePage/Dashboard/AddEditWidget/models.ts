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

export interface ConditionalOptions<T> {
  is: unknown;
  otherwise: T;
  then: T;
  when: string;
}

export interface ShowInput {
  contains?: Array<{ key: string; value: unknown }>;
  notContains?: Array<{ key: string; value: unknown }>;
  when: string;
}

export interface WidgetPropertyProps {
  className?: string;
  defaultValue?: unknown | ConditionalOptions<unknown>;
  disabled?: boolean;
  disabledCondition?: (values: Widget) => boolean;
  endAdornment?: ReactNode;
  label: string;
  options?: Array<SelectEntry> | ConditionalOptions<Array<SelectEntry>>;
  propertyName: string;
  required?: boolean;
  secondaryLabel?: Array<string> | string;
  show?: ShowInput;
  text?: {
    autoSize?: boolean;
    multiline?: boolean;
    size?: string;
    step?: string;
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
  criticalHighThreshold: number | null;
  criticalLowThreshold: number | null;
  unit: string;
  warningHighThreshold: number | null;
  warningLowThreshold: number | null;
}

export interface ServiceMetric extends NamedEntity {
  metrics: Array<Metric>;
  parentName: string;
  uuid: string;
}

export enum WidgetResourceType {
  host = 'host',
  hostCategory = 'host-category',
  hostGroup = 'host-group',
  service = 'service',
  serviceCategory = 'service-category',
  serviceGroup = 'service-group'
}

export enum RadioOptions {
  custom = 'custom',
  default = 'default',
  manual = 'manual'
}
