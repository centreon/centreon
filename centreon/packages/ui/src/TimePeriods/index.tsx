import { ReactNode } from 'react';

import { Responsive } from '@visx/visx';
import dayjs from 'dayjs';
import duration from 'dayjs/plugin/duration';
import isSameOrAfter from 'dayjs/plugin/isSameOrAfter';
import timezonePlugin from 'dayjs/plugin/timezone';
import utcPlugin from 'dayjs/plugin/utc';

import { Paper } from '@mui/material';

import CustomTimePeriod from './CustomTimePeriod';
import SelectedTimePeriod from './SelectedTimePeriod';
import {
  CustomTimePeriod as CustomTimePeriodModel,
  EndStartInterval,
  TimePeriod
} from './models';
import { useStyles } from './TimePeriods.styles';
import useAwesomeTimePeriod from './useAwesomeTimePeriod';

dayjs.extend(isSameOrAfter);
dayjs.extend(utcPlugin);
dayjs.extend(timezonePlugin);
dayjs.extend(duration);

export interface Props {
  adjustTimePeriodData?: CustomTimePeriodModel;
  disabled?: boolean;
  extraTimePeriods?: Array<TimePeriod>;
  getIsError?: (value: boolean) => void;
  getStartEndParameters?: ({ start, end }: EndStartInterval) => void;
  renderExternalComponent?: ReactNode;
}

const AwesomeTimePeriod = ({
  extraTimePeriods,
  disabled = false,
  getStartEndParameters,
  getIsError,
  adjustTimePeriodData,
  renderExternalComponent
}: Props): JSX.Element => {
  const { classes } = useStyles({ disabled });

  useAwesomeTimePeriod({
    adjustTimePeriodData,
    getIsError,
    getStartEndParameters
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
export default AwesomeTimePeriod;
