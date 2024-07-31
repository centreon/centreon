import { useEffect, useMemo, useRef, useState } from 'react';

import { useAtomValue } from 'jotai';
import dayjs from 'dayjs';
import { useTranslation } from 'react-i18next';
import { equals } from 'ramda';

import { Typography } from '@mui/material';

import { userAtom } from '@centreon/ui-context';

import { PanelOptions } from './models';
import { useClockStyles } from './Clock.styles';
import CustomFluidTypography from './CustomFluidTypography';
import { labelHour, labelMinute } from './translatedLabels';
import ClockInformation from './ClockInformation';

const Clock = ({
  timezone,
  locale,
  showTimezone,
  showDate,
  backgroundColor,
  timeFormat,
  hasDescription
}: PanelOptions & { hasDescription: boolean }): JSX.Element => {
  const { classes } = useClockStyles();
  const { t } = useTranslation();

  const [date, setDate] = useState(dayjs());
  const [showPoints, setShowPoints] = useState(true);
  const intervalRef = useRef<NodeJS.Timeout | null>(null);

  const user = useAtomValue(userAtom);

  const timezoneToUse = useMemo(
    () => (timezone?.id ?? user.timezone) as string,
    [user.timezone, timezone]
  );
  const localeToUse = useMemo(
    () => (locale?.id ?? user.locale) as string,
    [user.locale, locale]
  );

  const currentDate = date.locale(localeToUse).tz(timezoneToUse);

  const isMeridiem = useMemo(
    () =>
      equals(timeFormat, '12') ||
      dayjs().locale(localeToUse).format('LT').length > 5,
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
      setShowPoints((currentShowPoints) => !currentShowPoints);
    }, 2000);

    return () => {
      if (intervalRef.current) {
        clearInterval(intervalRef.current);
      }
    };
  }, []);

  return (
    <CustomFluidTypography>
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
              >{`${hours}${showPoints ? ':' : ' '}${minutes}`}</Typography>
              {isMeridiem && (
                <Typography
                  fontSize={fontSize / 3}
                  lineHeight={3}
                  sx={{ ml: 1 }}
                >
                  {meridiem}
                </Typography>
              )}
            </div>
            <div
              className={classes.clockHourMinuteSubLabel}
              style={{
                gridTemplateColumns: `1fr ${fontSize / 2}px 1fr`,
                paddingRight: isMeridiem ? fontSize - fontSize / 3 : 0,
                top: 30 + fontSize * 1.1 + 10
              }}
            >
              <Typography className={classes.icon} fontSize={fontSize / 2.8}>
                {t(labelHour)}
              </Typography>
              <div />
              <Typography className={classes.date} fontSize={fontSize / 2.8}>
                {t(labelMinute)}
              </Typography>
            </div>
          </div>
          <div
            className={classes.background}
            data-hasDescription={hasDescription}
            style={{
              backgroundColor: backgroundColor ?? '#255891'
            }}
          />
        </>
      )}
    </CustomFluidTypography>
  );
};

export default Clock;
