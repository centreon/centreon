import { ReactNode } from 'react';

import { Responsive } from '@visx/visx';
import dayjs from 'dayjs';
import 'dayjs/locale/en';
import 'dayjs/locale/es';
import 'dayjs/locale/fr';
import 'dayjs/locale/pt';
import duration from 'dayjs/plugin/duration';
import isSameOrAfter from 'dayjs/plugin/isSameOrAfter';
import timezonePlugin from 'dayjs/plugin/timezone';
import utcPlugin from 'dayjs/plugin/utc';

import { Paper } from '@mui/material';

import CustomTimePeriod from './CustomTimePeriod';
import SelectedTimePeriod from './SelectedTimePeriod';
import { useStyles } from './TimePeriods.styles';
import {
  CustomTimePeriod as CustomTimePeriodModel,
  EndStartInterval,
  TimePeriod
} from './models';
import useTimePeriod from './useTimePeriod';

dayjs.extend(isSameOrAfter);
dayjs.extend(utcPlugin);
dayjs.extend(timezonePlugin);
dayjs.extend(duration);
interface Parameters extends EndStartInterval {
  timelineEventsLimit: number;
}
export interface Props {
  adjustTimePeriodData?: Omit<CustomTimePeriodModel, 'timelineEventsLimit'>;
  disabled?: boolean;
  extraTimePeriods?: Array<Omit<TimePeriod, 'timelineEventsLimit'>>;
  getIsError?: (value: boolean) => void;
  getParameters?: ({ start, end, timelineEventsLimit }: Parameters) => void;
  renderExternalComponent?: ReactNode;
}

const TimePeriod = ({
  extraTimePeriods,
  disabled = false,
  getParameters,
  getIsError,
  adjustTimePeriodData,
  renderExternalComponent
}: Props): JSX.Element => {
  const { classes } = useStyles({ disabled });

  useTimePeriod({
    adjustTimePeriodData,
    getIsError,
    getParameters
  });

  return (
    <div>
      <Responsive.ParentSize>
        {({ width }): JSX.Element => {
          return (
            <Paper className={classes.header} style={{ width }}>
              <SelectedTimePeriod
                disabled={disabled}
                extraTimePeriods={extraTimePeriods}
                width={width}
              />
              <CustomTimePeriod disabled={disabled} />
              <div>{renderExternalComponent}</div>
            </Paper>
          );
        }}
      </Responsive.ParentSize>
    </div>
  );
};
export default TimePeriod;
