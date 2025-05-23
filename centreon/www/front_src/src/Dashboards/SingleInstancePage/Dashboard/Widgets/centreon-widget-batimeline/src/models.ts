export interface TimePeriod {
  end?: string | null;
  start?: string | null;
  timePeriodType: number;
}

export type PanelOptions = {
  refreshInterval: 'default' | 'custom' | 'manual';
  refreshIntervalCustom?: number;
  timeperiod: TimePeriod;
};
