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
import LoadingSkeleton from '../LoadingSkeleton';

import TimePeriods from './TimePeriods';
import { WrapperTimePeriodProps } from './models';

dayjs.extend(isSameOrAfter);
dayjs.extend(utcPlugin);
dayjs.extend(timezonePlugin);
dayjs.extend(duration);

const WrapperTimePeriods = ({
  skeletonHeight = 38,
  ...rest
}: WrapperTimePeriodProps): JSX.Element => (
  <ParentSize>
    {({ width }): JSX.Element => {
      return !width ? (
        <LoadingSkeleton height={skeletonHeight} variant="rectangular" />
      ) : (
        <TimePeriods width={width} {...rest} />
      );
    }}
  </ParentSize>
);

export default WrapperTimePeriods;
