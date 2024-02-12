import { LegendConfiguration, LegendProps } from '../Legend/models';

export interface BarType {
  color: string;
  label: string;
  value: number;
}

export type BarStackProps = {
  Legend: ({ scale, configuration }: LegendProps) => JSX.Element;
  Tooltip?: (barData) => JSX.Element;
  data: Array<BarType>;
  displayLegend?: boolean;
  displayValues?: boolean;
  legendConfiguration?: LegendConfiguration;
  onSingleBarClick?: (barData) => void;
  title?: string;
  unit?: 'percentage' | 'number';
  variant?: 'vertical' | 'horizontal';
};
