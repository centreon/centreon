import { LineChartData, Thresholds } from '../common/models';

export interface SingleBarProps {
  baseColor?: string;
  data?: LineChartData;
  displayAsRaw?: boolean;
  thresholds: Thresholds;
}
