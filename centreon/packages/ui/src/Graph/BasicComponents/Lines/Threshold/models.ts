import { ScaleLinear } from 'd3-scale';

export interface Data {
  lineColor: string;
  metric: string;
  yScale: ScaleLinear<number, number>;
}

export interface Point {
  x: number;
  y: number;
}

export interface Props {
  pointLower: Point;
  pointOrigin: Point;
  pointUpper: Point;
}
export interface Online extends Props {
  maxDistance?: number;
}
