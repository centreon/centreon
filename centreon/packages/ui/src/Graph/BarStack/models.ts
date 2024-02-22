export interface BarType {
  color: string;
  label: string;
  value: number;
}

export type BarStackProps = {
  Legend?: ({ scale, data, title, total }) => JSX.Element;
  data: Array<BarType>;
  displayLegend?: boolean;
  displayValues?: boolean;
  onSingleBarClick?: (barData) => void;
  size?: number;
  title?: string;
  tooltipContent?: (barData) => JSX.Element | boolean | null;
  unit?: 'percentage' | 'number';
  variant?: 'vertical' | 'horizontal';
};
