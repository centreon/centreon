import { Line, Metric, TimeValue } from './timeSeries/models';
import { AxisX, Axis as AxisYLeft, AxisYRight } from './Axes/models';
import { AreaRegularLines, AreaStackedLines } from './Lines/models';

export interface GraphData {
  global;
  metrics: Array<Metric>;
  times: Array<string>;
}

export interface GraphEndpoint {
  baseUrl: string;
  queryParameters: GraphParameters;
}

export interface Data {
  baseAxis: number;
  endpoint: GraphEndpoint;
  lines: Array<Line>;
  timeSeries: Array<TimeValue>;
  title: string;
}

export interface GraphParameters {
  end: Date;
  start: Date;
}

export interface AnchorPoint {
  [x: string]: unknown;
  display: boolean;
}

export interface AreaAnchorPoint {
  areaRegularLinesAnchorPoint: AnchorPoint;
  areaStackedLinesAnchorPoint: AnchorPoint;
}

export interface ShapeLines {
  areaRegularLines?: AreaRegularLines;
  areaStackedLines?: AreaStackedLines;
}

export interface Axis {
  axisX?: AxisX;
  axisYLeft?: AxisYLeft;
  axisYRight?: AxisYRight;
}

export interface GridsModel {
  column: Record<string, unknown>;
  row: Record<string, unknown>;
}
