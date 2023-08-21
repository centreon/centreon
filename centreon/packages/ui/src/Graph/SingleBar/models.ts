import { LineChartData } from '../common/models';

export interface SingleBarProps {
  data: LineChartData;
  thresholdTooltipLabels: Array<string>;
  thresholds: Array<number>;
}
