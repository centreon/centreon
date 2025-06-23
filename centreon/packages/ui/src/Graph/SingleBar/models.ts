import { LineChartData, Thresholds } from '../common/models';

export interface SingleBarProps {
  baseColor?: string;
  data?: LineChartData;
  displayAsRaw?: boolean;
  showLabels?: boolean;
  size?: 'medium' | 'small';
  thresholds: Thresholds;
  max?: number;
}
