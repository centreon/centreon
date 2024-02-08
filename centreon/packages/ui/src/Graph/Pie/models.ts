interface DataType {
  color: string;
  label: string;
  value: number;
}

export interface PieProps {
  data: Array<DataType>;
  displayValue?: boolean;
  legend?: boolean;
  title?: string;
  unit?: 'Percentage' | 'Number';
  variant?: 'Pie' | 'Donut';
}
