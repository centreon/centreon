import { Line, Metric, TimeValue } from './timeSeries/models';
import { AxisX, Axis as AxisYLeft, AxisYRight } from './Axes/models';
import { AreaRegularLines, AreaStackedLinesData } from './Lines/models';

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
  graphEndpoint: GraphEndpoint;
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
  areaRegularLinesData?: AreaRegularLines;
  areaStackedLinesData?: AreaStackedLinesData;
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
