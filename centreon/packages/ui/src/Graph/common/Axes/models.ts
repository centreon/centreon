import { Line, TimeValue } from '../timeSeries/models';
import { LineChartAxis } from '../../LineChart/models';

export interface LabelProps {
  [x: string]: unknown;
  textAnchor?: string;
}

export interface Axis {
  displayUnit?: boolean;
}

export interface AxisYRight extends Axis {
  display?: boolean;
}

export interface AxisX {
  xAxisTickFormat?: string;
}
export interface Data
  extends Omit<LineChartAxis, 'axisX' | 'axisYLeft' | 'axisYRight'> {
  axisX?: AxisX;
  axisYLeft?: Axis;
  axisYRight?: AxisYRight;
  baseAxis: number;
  lines: Array<Line>;
  timeSeries: Array<TimeValue>;
}
