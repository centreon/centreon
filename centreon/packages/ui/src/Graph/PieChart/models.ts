export interface ArcType {
  color: string;
  label: string;
  value: number;
}

export interface PieProps {
  Legend?: ({ scale, data, title, total }) => JSX.Element;
  data: Array<ArcType>;
  displayLegend?: boolean;
  displayValues?: boolean;
  innerRadius?: number;
  onArcClick?: (ardata) => void;
  title?: string;
  tooltipContent?: (arcData) => JSX.Element | boolean | null;
  unit?: 'percentage' | 'number';
  variant?: 'pie' | 'donut';
}
