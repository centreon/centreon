import { ReactNode } from 'react';

import { ScaleLinear } from 'd3-scale';

import { Line, TimeValue } from '../common/timeSeries/models';
import { LineChartData } from '../common/models';

import {
  AxisX,
  Axis as AxisYLeft,
  AxisYRight
} from './BasicComponents/Axes/models';
import {
  AreaRegularLines,
  AreaStackedLines
} from './BasicComponents/Lines/models';
import { TimelineEvent } from './InteractiveComponents/Annotations/models';
import { FactorsVariation } from './BasicComponents/Lines/Threshold/models';

export interface LineChartEndpoint {
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
  renderComponent?: (args: TooltipData) => ReactNode;
}

export interface AnnotationEvent {
  data?: Array<TimelineEvent>;
}

export interface LineChartHeader {
  displayTitle?: boolean;
  extraComponent?: ReactNode;
}

export interface DisplayAnchor {
  displayGuidingLines?: boolean;
  displayTooltipsGuidingLines?: boolean;
}

export interface LineChartProps {
  annotationEvent?: AnnotationEvent;
  axis?: Axis;
  displayAnchor?: DisplayAnchor;
  header?: LineChartHeader;
  height?: number | null;
  loading: boolean;
  timeShiftZones?: InteractedZone;
  tooltip?: Tooltip;
  width: number;
  zoomPreview?: InteractedZone;
}

export interface Area {
  display: boolean;
}

export type PatternOrientation =
  | 'diagonal'
  | 'diagonalRightToLeft'
  | 'horizontal'
  | 'vertical';

export enum ThresholdType {
  basic = 'basic',
  pattern = 'pattern',
  variation = 'variation'
}

export interface PatternThreshold {
  data: Array<LineChartData>;
  orientation?: Array<PatternOrientation>;
  type: ThresholdType.pattern;
}
export interface VariationThreshold {
  displayCircles?: boolean;
  factors: FactorsVariation;
  getCountDisplayedCircles?: (value: number) => void;
  type: ThresholdType.variation;
}

export interface BasicThreshold {
  type: ThresholdType.basic;
}

export interface GlobalAreaLines {
  areaRegularLines?: Area;
  areaStackedLines?: Area;
  areaThresholdLines?: Array<
    PatternThreshold | VariationThreshold | BasicThreshold
  >;
}
export interface LegendModel {
  display?: boolean;
  renderExtraComponent?: ReactNode;
}

export interface GetDate {
  positionX: number;
  timeSeries: Array<TimeValue>;
  xScale: ScaleLinear<number, number>;
}

export interface GraphTooltipData {
  date: string;
  highlightedMetricId: number | null;
  metrics: Array<{
    color: string;
    id: number;
    name: string;
    unit: string;
    value: number;
  }>;
}
