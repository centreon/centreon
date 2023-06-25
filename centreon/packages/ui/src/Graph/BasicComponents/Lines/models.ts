import { ScaleLinear } from 'd3-scale';

import { GraphData } from '../../models';
import { Line, TimeValue } from '../../timeSeries/models';

import { FactorsVariation } from './Threshold/models';

export interface ShapeGraphData {
  [x: string]: unknown;
  display: boolean;
  leftScale?: ScaleLinear<number, number>;
  rightScale?: ScaleLinear<number, number>;
  xScale?: ScaleLinear<number, number>;
  yScale?: ScaleLinear<number, number>;
}

export interface LinesData {
  lines: Array<Line>;
  timeSeries: Array<TimeValue>;
}

export interface AreaStackedLines extends ShapeGraphData {
  invertedStackedLinesData: LinesData;
  stackedLinesData: LinesData;
}

export interface AreaRegularLines extends ShapeGraphData {
  lines: Array<Line>;
  timeSeries: Array<TimeValue>;
}

export interface AreaThreshold extends AreaRegularLines {
  dataExclusionPeriods?: Array<GraphData>;
  factors?: FactorsVariation;
  getCountDisplayedCircles?: (value: number) => void;
}

export interface Shape {
  areaRegularLines: AreaRegularLines;
  areaStackedLines: AreaStackedLines;
  areaThreshold: AreaThreshold;
}
