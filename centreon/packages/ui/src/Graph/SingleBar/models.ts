import { LineChartData, Thresholds } from '../common/models';

export interface SingleBarProps {
  data?: LineChartData;
  displayAsRaw?: boolean;
  thresholds: Thresholds;
}
