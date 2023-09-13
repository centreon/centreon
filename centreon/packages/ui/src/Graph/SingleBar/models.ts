import { LineChartData, Thresholds } from '../common/models';

export interface SingleBarProps {
  baseColor?: string;
  data?: LineChartData;
  displayAsRaw?: boolean;
  size?: 'medium' | 'small';
  thresholds: Thresholds;
}
