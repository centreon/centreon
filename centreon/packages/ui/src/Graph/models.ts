import { Line, Metric, TimeValue } from './timeSeries/models';
import { AxisX, Axis as AxisYLeft, AxisYRight } from './Axes/models';
import { AreaRegularLines, AreaStackedLines } from './Lines/models';
import { Interval } from './InteractionWithGraph/ZoomPreview/models';

export interface GraphData {
  global;
  metrics: Array<Metric>;
  times: Array<string>;
}

export interface GraphEndpoint {
  baseUrl: string;
  queryParameters: GraphInterval;
}
export interface Data {
  baseAxis: number;
  lines: Array<Line>;
  timeSeries: Array<TimeValue>;
  title: string;
}

export enum GraphIntervalProperty {
  end = 'end',
  start = 'start'
}

export interface GraphInterval {
  end?: string;
  start?: string;
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
  display?: boolean;
  getZoomInterval?: (data: Interval) => void;
}

export interface InteractedZone {
  enable?: boolean;
  getInterval?: (data: GraphInterval) => void;
}

export interface GraphProps {
  anchorPoint?: AreaAnchorPoint;
  axis?: Axis;
  height: number;
  timeShiftZones?: InteractedZone;
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
