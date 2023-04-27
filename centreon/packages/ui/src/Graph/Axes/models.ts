import { Line, TimeValue } from '../timeSeries/models';

export interface LabelProps {
  [x: string]: unknown;
  textAnchor?: string;
}

export interface Axis {
  [x: string]: unknown;
  displayUnit?: boolean;
}

export interface AxisYRight extends Axis {
  display?: boolean;
}

export interface AxisX {
  [x: string]: unknown;
  xAxisTickFormat: string;
}
export interface Data {
  axisX?: AxisX;
  axisYLeft?: Axis;
  axisYRight?: AxisYRight;
  baseAxis: number;
  lines: Array<Line>;
  timeSeries: Array<TimeValue>;
}
