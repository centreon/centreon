import { ScaleLinear } from 'd3-scale';

import { TimeValue } from '../../../timeSeries/models';

export interface Data {
  lineColor: string;
  metric: string;
  yScale: ScaleLinear<number, number>;
}

export interface Point {
  x: number;
  y: number;
}

export interface ArePointsOnline {
  pointLower: Point;
  pointOrigin: Point;
  pointUpper: Point;
}
export interface Online extends ArePointsOnline {
  maxDistance?: number;
}

export interface FactorsVariation {
  currentFactorMultiplication: number;
  simulatedFactorMultiplication: number;
}

export interface Result {
  getX: ScaleLinear<number, number>;
  getY0: ScaleLinear<number, number>;
  getY1: ScaleLinear<number, number>;
  lineColorY0: string;
  lineColorY1: string;
}

export interface GetEnvelopeVariation {
  factorsData: FactorsVariation;
  metricLower: string;
  metricUpper: string;
  timeValue: TimeValue;
}

export interface Circle {
  dataY0: Data;
  dataY1: Data;
  dataYOrigin: Data;
  factors: FactorsVariation;
  getCountDisplayedCircles?: (value: number) => void;
  timeSeries: Array<TimeValue>;
  xScale: ScaleLinear<number, number>;
}
