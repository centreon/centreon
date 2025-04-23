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

export type ValueFormat = 'human' | 'raw';

export interface TopBottomSettings {
  numberOfValues: number;
  order: 'top' | 'bottom';
  showLabels: boolean;
}
