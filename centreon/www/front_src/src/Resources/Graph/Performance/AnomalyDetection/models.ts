import { Line, TimeValue } from '../models';

export interface FactorsData {
  currentFactor: number;
  simulatedFactor: number;
}

export interface CustomFactorsData extends FactorsData {
  isResizing: boolean;
}

export interface ExclusionPeriodThresholdData {
  comment?: string;
  id: SelectedDateToDelete;
  isConfirmed: boolean;
  lines: Array<Line>;
  timeSeries: Array<TimeValue>;
}

export interface SelectedDateToDelete {
  end: Date | '';
  start: Date | '';
}

export interface ExclusionPeriodsThreshold {
  data: Array<ExclusionPeriodThresholdData>;
  selectedDateToDelete?: Array<SelectedDateToDelete>;
}
