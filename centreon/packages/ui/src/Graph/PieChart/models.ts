export interface ArcType {
  color: string;
  label: string;
  value: number;
}

export interface PieProps {
  Legend?: ({ scale, direction, data, title, total, unit }) => JSX.Element;
  TooltipContent?: (arcData) => JSX.Element | boolean | null;
  data: Array<ArcType>;
  displayLegend?: boolean;
  displayTitle?: boolean;
  displayTotal?: boolean;
  displayValues?: boolean;
  innerRadius?: number;
  innerRadiusNoLimit?: boolean;
  legendDirection?: 'row' | 'column';
  onArcClick?: (arcData) => void;
  opacity: number;
  padAngle?: number;
  title?: string;
  titlePosition?: 'default' | 'bottom';
  tooltipProps?: object;
  unit?: 'percentage' | 'number';
  variant?: 'pie' | 'donut';
}
