import { ChartAxis } from '../../Chart/models';
import { Line, TimeValue } from '../timeSeries/models';

export interface LabelProps {
  [x: string]: unknown;
  textAnchor?: string;
}

export interface Axis {
  displayUnit?: boolean;
  onUnitChange?: (newUnit: string) => void;
  unit?: string;
}

export interface AxisYRight extends Axis {
  display?: boolean;
}

export interface AxisX {
  xAxisTickFormat?: string;
}
export interface Data
  extends Omit<ChartAxis, 'axisX' | 'axisYLeft' | 'axisYRight'> {
  axisX?: AxisX;
  axisYLeft?: Axis;
  axisYRight?: AxisYRight;
  baseAxis: number;
  lines: Array<Line>;
  timeSeries: Array<TimeValue>;
}
