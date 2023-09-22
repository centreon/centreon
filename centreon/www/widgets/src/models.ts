export interface FormThreshold {
  criticalType: 'default' | 'custom';
  customCritical: number;
  customWarning: number;
  enabled: boolean;
  warningType: 'default' | 'custom';
}

export interface GlobalRefreshInterval {
  interval: number | null;
  type: 'global' | 'manual';
}
