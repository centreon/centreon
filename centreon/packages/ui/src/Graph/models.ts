import { ScaleLinear } from 'd3-scale';

import { Line, Metric, TimeValue } from '../timeSeries/models';

export interface GraphData {
  global;
  metrics: Array<Metric>;
  times: Array<string>;
}

export interface Data {
  baseAxis: number;
  lines: Array<Line>;
  timeSeries: Array<TimeValue>;
}

export interface shapeGraphData {
  [x: string]: unknown;
  leftScale?: ScaleLinear<number, number>;
  lines: Array<Line>;
  rightScale?: ScaleLinear<number, number>;
  timeSeries: Array<TimeValue>;
  xScale?: ScaleLinear<number, number>;
  yScale?: ScaleLinear<number, number>;
}

export interface ShapeLines {
  areaRegularLinesData?: shapeGraphData;
  areaStackedLinesData?: shapeGraphData;
}

export interface Axis {
  axisX: Record<string, unknown>;
  axisYLeft: Record<string, unknown>;
  axisYRight: Record<string, unknown>;
}

export interface GridsModel {
  column: Record<string, unknown>;
  row: Record<string, unknown>;
}
