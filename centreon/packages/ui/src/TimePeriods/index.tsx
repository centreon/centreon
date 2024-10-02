import dayjs from 'dayjs';
import 'dayjs/locale/en';
import 'dayjs/locale/es';
import 'dayjs/locale/fr';
import 'dayjs/locale/pt';
import duration from 'dayjs/plugin/duration';
import isSameOrAfter from 'dayjs/plugin/isSameOrAfter';
import timezonePlugin from 'dayjs/plugin/timezone';
import utcPlugin from 'dayjs/plugin/utc';

import { ParentSize } from '..';

import { WrapperTimePeriodProps } from './models';
import TimePeriods from './TimePeriods';

dayjs.extend(isSameOrAfter);
dayjs.extend(utcPlugin);
dayjs.extend(timezonePlugin);
dayjs.extend(duration);

const WrapperTimePeriods = (props: WrapperTimePeriodProps): JSX.Element => (
  <ParentSize>
    {({ width }): JSX.Element => {
      return <TimePeriods width={width} {...props} />;
    }}
  </ParentSize>
);

export default WrapperTimePeriods;
