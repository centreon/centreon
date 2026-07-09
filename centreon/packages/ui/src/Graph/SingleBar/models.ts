import type { LineChartData, Thresholds } from '../common/models';

export interface SingleBarProps {
  baseColor?: string;
  data?: LineChartData;
  displayAsRaw?: boolean;
  showLabels?: boolean;
  size?: 'medium' | 'small';
  thresholds: Thresholds;
  max?: number;
  direction?: 'column' | 'row';
  textWidth?: number; // Applied only when the direction is row
}
