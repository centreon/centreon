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
  lines: Array<Line>;
  queryParameters: GraphParameters;
  timeSeries: Array<TimeValue>;
  title: string;
}

export interface GraphParameters {
  end: string;
  start: string;
}

export interface AnchorPoint {
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

export interface ZoomPreview {
  [x: string]: unknown;
  display?: boolean;
}

export interface GraphProps {
  anchorPoint?: AreaAnchorPoint;
  axis?: Axis;
  height: number;
  width: number;
  zoomPreview?: ZoomPreview;
}

export interface Area {
  display: boolean;
}

export interface GlobalAreaLines {
  areaRegularLines: Area;
  areaStackedLines: Area;
}
