import type { LegendScale } from '../Legend/models';

export interface BarType {
  color: string;
  label: string;
  value: number;
}

export interface LegendProps {
  scale: LegendScale;
  data: Array<BarType>;
  title?: string;
  total: number;
  unit?: 'percentage' | 'number';
  direction?: 'row' | 'column';
}

export interface TooltipContentProps {
  color: string;
  label: string;
  total: number;
  value: number;
}

export type BarStackProps = {
  Legend?: (props: LegendProps) => JSX.Element;
  TooltipContent?: (
    barData: TooltipContentProps
  ) => JSX.Element | boolean | null;
  data: Array<BarType>;
  displayLegend?: boolean;
  displayValues?: boolean;
  legendDirection?: 'row' | 'column';
  onSingleBarClick?: (barData: BarType) => void;
  title?: string;
  tooltipProps?: object;
  unit?: 'percentage' | 'number';
  variant?: 'vertical' | 'horizontal';
};
