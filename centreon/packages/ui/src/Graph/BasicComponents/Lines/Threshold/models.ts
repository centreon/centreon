import { ScaleLinear } from 'd3-scale';

export interface Data {
  lineColor: string;
  metric: string;
  yScale: ScaleLinear<number, number>;
}
