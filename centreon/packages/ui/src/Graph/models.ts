import { ReactNode } from 'react';

import { Line, Metric, TimeValue } from './timeSeries/models';
import {
  AxisX,
  Axis as AxisYLeft,
  AxisYRight
} from './BasicComponents/Axes/models';
import {
  AreaRegularLines,
  AreaStackedLines
} from './BasicComponents/Lines/models';
import { TimelineEvent } from './IntercatifsComponents/Annotations/models';
import { FactorsVariation } from './BasicComponents/Lines/Threshold/models';

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

export interface Interval {
  end: Date;
  start: Date;
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

export interface InteractedZone {
  enable?: boolean;
  getInterval?: (data: Interval) => void;
}

export interface TooltipData {
  data: Date;
  hideTooltip: () => void;
  tooltipOpen: boolean;
}
export interface Tooltip {
  enable?: boolean;
  renderComponent?: (args: TooltipData) => ReactNode;
}

export interface AnnotationEvent {
  data?: Array<TimelineEvent>;
}

export interface HeaderGraph {
  displayTitle?: boolean;
  extraComponent?: ReactNode;
}

export interface GraphProps {
  anchorPoint?: AreaAnchorPoint;
  annotationEvent?: AnnotationEvent;
  axis?: Axis;
  header?: HeaderGraph;
  height?: number;
  loading: boolean;
  timeShiftZones?: InteractedZone;
  tooltip?: Tooltip;
  width: number;
  zoomPreview?: InteractedZone;
}

export interface Area {
  display: boolean;
}

export interface ThresholdArea extends Area {
  dataExclusionPeriods?: Array<GraphData>;
  factors?: FactorsVariation;
  getCountDisplayedCircles?: (value: number) => void;
}

export interface GlobalAreaLines {
  areaRegularLines?: Area;
  areaStackedLines?: Area;
  areaThresholdLines?: ThresholdArea;
}
export interface LegendModel {
  renderExtraComponent?: ReactNode;
}
