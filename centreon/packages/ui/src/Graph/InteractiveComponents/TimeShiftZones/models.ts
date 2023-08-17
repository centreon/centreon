import { GraphInterval, GraphIntervalProperty } from '../../models';

export enum TimeShiftDirection {
  backward,
  forward
}

export interface GetShiftDate {
  property: GraphIntervalProperty;
  timePeriod: GraphInterval;
  timeShiftDirection: TimeShiftDirection;
}
