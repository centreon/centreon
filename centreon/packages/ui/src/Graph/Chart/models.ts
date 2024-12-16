import type { ReactNode } from 'react';

import type { ScaleLinear } from 'd3-scale';

import type { BarStyle } from '../BarChart/models';
import type {
  AxisX,
  Axis as AxisYLeft,
  AxisYRight
} from '../common/Axes/models';
import type { LineChartData } from '../common/models';
import type { Line, TimeValue } from '../common/timeSeries/models';

import type { FactorsVariation } from './BasicComponents/Lines/Threshold/models';
import type {
  AreaRegularLines,
  AreaStackedLines
} from './BasicComponents/Lines/models';
import type { TimelineEvent } from './InteractiveComponents/Annotations/models';

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

export interface ChartAxis {
  axisX?: AxisX;
  axisYLeft?: AxisYLeft;
  axisYRight?: AxisYRight;
  gridLinesType?: 'horizontal' | 'vertical' | 'all';
  isCenteredZero?: boolean;
  scale?: 'linear' | 'logarithmic';
  scaleLogarithmicBase?: number;
  showBorder?: boolean;
  showGridLines?: boolean;
  yAxisTickLabelRotation?: number;
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
export interface ThresholdTooltip {
  thresholdLabel?: string;
}
export interface Tooltip {
  mode: 'all' | 'single' | 'hidden';
  renderComponent?: (args: TooltipData) => ReactNode;
  sortOrder: 'name' | 'ascending' | 'descending';
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

export interface LineStyle {
  areaTransparency?: number;
  curve?: 'linear' | 'step' | 'natural';
  dashLength?: number;
  dashOffset?: number;
  dotOffset?: number;
  lineWidth?: number;
  pathStyle?: 'solid' | 'dash' | 'dotted';
  showArea?: boolean;
  showPoints?: boolean;
}

export interface LineChartProps {
  annotationEvent?: AnnotationEvent;
  axis?: ChartAxis;
  barStyle?: BarStyle;
  displayAnchor?: DisplayAnchor;
  header?: LineChartHeader;
  height?: number | null;
  legend?: LegendModel;
  lineStyle?: LineStyle;
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
  id: string;
}
export interface VariationThreshold {
  displayCircles?: boolean;
  factors: FactorsVariation;
  getCountDisplayedCircles?: (value: number) => void;
  type: ThresholdType.variation;
  id: string;
}

export interface BasicThreshold {
  id: string;
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
  height?: number;
  mode: 'grid' | 'list';
  placement: 'bottom' | 'left' | 'right';
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
