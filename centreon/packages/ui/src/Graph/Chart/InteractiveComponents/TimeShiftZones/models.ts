import { GraphInterval, GraphIntervalProperty } from '../../models';

export enum TimeShiftDirection {
  backward = 0,
  forward = 1
}

export interface GetShiftDate {
  property: GraphIntervalProperty;
  timePeriod: GraphInterval;
  timeShiftDirection: TimeShiftDirection;
}
