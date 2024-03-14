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
  displayValues?: boolean;
  innerRadius?: number;
  labelNoDataFound?: string;
  legendDirection?: 'row' | 'column';
  onArcClick?: (ardata) => void;
  title?: string;
  unit?: 'percentage' | 'number';
  variant?: 'pie' | 'donut';
}
