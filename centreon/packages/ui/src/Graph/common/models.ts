import { Metric } from './timeSeries/models';

export interface LineChartData {
  global;
  metrics: Array<Metric>;
  times: Array<string>;
}
