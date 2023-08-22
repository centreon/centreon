import { LineChartData } from '../common/models';

export interface SingleBarProps {
  data?: LineChartData;
  disabledThresholds?: boolean;
  thresholdTooltipLabels: Array<string>;
  thresholds: Array<number>;
}
