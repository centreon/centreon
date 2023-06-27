export interface FormattedMetricData {
  color: string;
  formattedValue: string | null;
  name: string;
  unit: string;
}

export interface GetMetricValueProps {
  unit: string;
  value: number | null;
}
