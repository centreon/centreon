import { LegendConfiguration, LegendProps } from '../Legend/models';

export interface ArcType {
  color: string;
  label: string;
  value: number;
}

export interface PieProps {
  Legend: ({ scale, configuration }: LegendProps) => JSX.Element;
  data: Array<ArcType>;
  displayLegend?: boolean;
  displayValues?: boolean;
  innerRadius?: number;
  legendConfiguration?: LegendConfiguration;
  onArcClick?: (ardata) => void;
  title?: string;
  tooltipContent?: (arcData) => JSX.Element | boolean | null;
  unit?: 'percentage' | 'number';
  variant?: 'pie' | 'donut';
}
