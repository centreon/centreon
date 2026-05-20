import type { LegendProps as BaseLegendProps } from '../Legend/models';

export interface ArcType {
  color: string;
  label: string;
  value: number;
}

export interface PieLegendProps extends BaseLegendProps {
  data: Array<ArcType>;
  title?: string;
  total: number;
  unit?: 'percentage' | 'number';
}

export interface PieTooltipContentProps {
  color: string;
  label: string;
  title?: string;
  total: number;
  value: number;
}

export interface PieProps {
  Legend?: (props: PieLegendProps) => JSX.Element;
  TooltipContent?: (
    arcData: PieTooltipContentProps
  ) => JSX.Element | boolean | null;
  data: Array<ArcType>;
  displayLegend?: boolean;
  displayTotal?: boolean;
  displayValues?: boolean;
  innerRadius?: number;
  innerRadiusNoLimit?: boolean;
  legendDirection?: 'row' | 'column';
  onArcClick?: (arcData: ArcType) => void;
  opacity: number;
  padAngle?: number;
  title?: string;
  tooltipProps?: object;
  unit?: 'percentage' | 'number';
  variant?: 'pie' | 'donut';
}
