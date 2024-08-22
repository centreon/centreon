import { createStore } from 'jotai';
import { QueryClient } from '@tanstack/react-query';

import { SelectEntry } from '@centreon/ui';

export interface FormThreshold {
  criticalType: 'default' | 'custom';
  customCritical: number;
  customWarning: number;
  enabled: boolean;
  warningType: 'default' | 'custom';
}

export interface FormTimePeriod {
  end?: string | null;
  start?: string | null;
  timePeriodType: number;
}

export interface GlobalRefreshInterval {
  interval: number | null;
  type: 'global' | 'manual';
}

export interface Resource {
  resourceType: string;
  resources: Array<SelectEntry>;
}

export interface NamedEntity {
  id: number;
  name: string;
}

export interface MetricResource {
  id: number;
  name: string;
  parentName: string;
  uuid: string;
}

export interface Metric {
  criticalHighThreshold: number | null;
  criticalLowThreshold: number | null;
  excludedMetrics: Array<number>;
  id: number;
  includeAllResources?: boolean;
  name: string;
  unit: string;
  warningHighThreshold: number | null;
  warningLowThreshold: number | null;
}

export enum SortOrder {
  Asc = 'asc',
  Desc = 'desc'
}

export interface ServiceMetric extends NamedEntity {
  metrics: Array<Metric>;
}

export interface Data {
  metrics: Array<Metric>;
  resources: Array<Resource>;
}

export interface DataWithoutMetrics {
  resources: Array<Resource>;
}

export enum SeverityStatus {
  Pending = 'pending',
  Problem = 'problem',
  Success = 'success',
  Undefined = 'undefined',
  Warning = 'warning'
}

export interface CommonWidgetProps<T extends object> {
  dashboardId: number | string;
  globalRefreshInterval: GlobalRefreshInterval;
  hasDescription: boolean;
  id: string;
  isFromPreview?: boolean;
  playlistHash?: string;
  queryClient: QueryClient;
  refreshCount: number;
  setPanelOptions?: (panelOptions: Partial<T>) => void;
  store: ReturnType<typeof createStore>;
  widgetPrefixQuery: string;
}

export type StatusDetail = {
  acknowledged: number;
  in_downtime: number;
  total: number;
};

export type Status =
  | 'critical'
  | 'warning'
  | 'unknown'
  | 'pending'
  | 'ok'
  | 'down'
  | 'unreachable'
  | 'up';

export type StatusType = {
  [key in Status]: StatusDetail;
} & {
  total: number;
};
