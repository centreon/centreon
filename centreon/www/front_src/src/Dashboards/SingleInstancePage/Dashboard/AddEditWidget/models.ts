import { ReactNode } from 'react';

import { SelectEntry } from '@centreon/ui';

import { SubInput } from '../../../../federatedModules/models';
import { PanelConfiguration, WidgetOptions } from '../models';

export interface Widget {
  data: object | null;
  id: string | null;
  moduleName: string | null;
  options: WidgetOptions;
  panelConfiguration: PanelConfiguration | null;
}

export interface ShowInput {
  contains?: Array<{ key: string; value: unknown }>;
  notContains?: Array<{ key: string; value: unknown }>;
  when: string;
}

export interface WidgetPropertyProps {
  baseEndpoint: string;
  className?: string;
  datePicker?: {
    maxDays?: number;
  };
  defaultValue?: unknown;
  disabled?: boolean;
  disabledCondition?: (values: Widget) => boolean;
  endAdornment?: ReactNode;
  excludedResourceTypes?: Array<string>;
  isInGroup: boolean;
  isSingleAutocomplete: boolean;
  keepOneOptionSelected?: boolean;
  label: string;
  options?: Array<SelectEntry>;
  propertyName: string;
  requireResourceType?: boolean;
  required?: boolean;
  restrictedResourceTypes?: Array<string>;
  secondaryLabel?: Array<string> | string;
  show?: ShowInput;
  singleResourceType?: boolean;
  slider?: {
    max: number;
    min: number;
    unit?: string;
  };
  subInputs?: Array<SubInput>;
  text?: {
    autoSize?: boolean;
    max?: number;
    min?: number;
    multiline?: boolean;
    size?: string;
    step?: string;
    type?: string;
  };
  type: string;
  useAdditionalResources?: boolean;
}

export interface WidgetDataResource {
  resourceType: WidgetResourceType;
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

export interface FormMetric extends Metric {
  excludedMetrics: Array<number>;
  includeAllMetrics?: boolean;
}

export interface ServiceMetric extends NamedEntity {
  id: number;
  metrics: Array<Metric>;
  name: string;
  parentName: string;
  uuid: string;
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

export enum RadioOptions {
  custom = 'custom',
  default = 'default',
  manual = 'manual'
}
