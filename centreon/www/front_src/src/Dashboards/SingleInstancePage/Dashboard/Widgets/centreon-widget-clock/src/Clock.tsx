import { memo, useEffect, useMemo, useRef, useState } from 'react';

import dayjs from 'dayjs';
import { equals } from 'ramda';

import { Typography } from '@mui/material';

import BackgroundColor from './BackgroundColor';
import { useClockStyles } from './Clock.styles';
import ClockInformation from './ClockInformation';
import CustomFluidTypography from './CustomFluidTypography';
import { ForceDimension, PanelOptions } from './models';
import { useGetLocaleAndTimezone } from './useGetLocaleAndTimezone';

const Clock = ({
  timezone,
  locale,
  showTimezone,
  showDate,
  backgroundColor,
  timeFormat,
  hasDescription,
  forceWidth,
  forceHeight
}: PanelOptions &
  ForceDimension & { hasDescription: boolean }): JSX.Element => {
  const { classes } = useClockStyles();

  const [date, setDate] = useState(dayjs());
  const intervalRef = useRef<number | null>(null);

  const { locale: localeToUse, timezone: timezoneToUse } =
    useGetLocaleAndTimezone({ locale, timezone });

  const currentDate = date.locale(localeToUse).tz(timezoneToUse);

  const isMeridiem = useMemo(
    () =>
      timeFormat
        ? equals(timeFormat, '12')
        : dayjs().locale(localeToUse).format('LT').length > 5,
    [localeToUse, timeFormat]
  );

  const hours = useMemo(
    () => currentDate.format(isMeridiem ? 'hh' : 'HH'),
    [isMeridiem, currentDate]
  );
  const minutes = useMemo(() => currentDate.format('mm'), [currentDate]);
  const meridiem = useMemo(() => currentDate.format('A'), [currentDate]);

  useEffect(() => {
    intervalRef.current = setInterval(() => {
      setDate(dayjs());
    }, 5000);

    return () => {
      if (intervalRef.current) {
        clearInterval(intervalRef.current);
      }
    };
  }, []);

  return (
    <CustomFluidTypography forceHeight={forceHeight} forceWidth={forceWidth}>
      {({ width, fontSize }) => (
        <>
          <div className={classes.container}>
            <ClockInformation
              isClock
              date={currentDate}
              showDate={showDate}
              showTimezone={showTimezone}
              timezone={timezoneToUse}
              width={width}
            />
            <div className={classes.clockLabel}>
              <Typography
                fontSize={fontSize}
              >{`${hours}:${minutes}`}</Typography>
              {isMeridiem && (
                <Typography
                  fontSize={fontSize / 3}
                  lineHeight={3}
                  style={{ marginBottom: fontSize / 2 }}
                  sx={{ ml: 1 }}
                >
                  {meridiem}
                </Typography>
              )}
            </div>
          </div>
          <BackgroundColor
            backgroundColor={backgroundColor}
            hasDescription={hasDescription}
          />
        </>
      )}
    </CustomFluidTypography>
  );
};

export default memo(Clock, equals);
