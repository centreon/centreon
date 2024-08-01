import { memo } from 'react';

import dayjs from 'dayjs';
import { useTranslation } from 'react-i18next';
import { equals } from 'ramda';

import { Typography } from '@mui/material';

import { useClockStyles } from './Clock.styles';
import ClockInformation from './ClockInformation';
import { PanelOptions } from './models';
import {
  labelDay,
  labelHour,
  labelMinute,
  labelSecond
} from './translatedLabels';
import CustomFluidTypography from './CustomFluidTypography';
import BackgroundColor from './BackgroundColor';
import { useTimer } from './useTimer';

const formatTimerPart = (timerPart: number): string => {
  if (timerPart <= 0) {
    return '00';
  }

  return timerPart < 10 ? `0${timerPart}` : `${timerPart}`;
};

const Timer = ({
  countdown,
  showDate,
  showTimezone,
  timezone,
  locale,
  hasDescription,
  backgroundColor
}: PanelOptions & { hasDescription: boolean }): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useClockStyles();

  const {
    date: currentDate,
    dateFromCountDown,
    timezone: timezoneToUse,
    locale: localeToUse,
    countdownHasEnded
  } = useTimer({
    countdown,
    locale,
    timezone
  });

  const diff = dateFromCountDown.diff(currentDate);
  const duration = dayjs.duration(diff);

  const daysRemaining = duration.days();
  const hoursRemaining = formatTimerPart(duration.hours());
  const minutesRemaining = formatTimerPart(duration.minutes());
  const secondsRemaining = formatTimerPart(duration.seconds());

  const displayFromDays = daysRemaining > 0;

  const clockLabel = displayFromDays
    ? `${formatTimerPart(daysRemaining)}:${hoursRemaining}:${minutesRemaining}`
    : `${hoursRemaining}:${minutesRemaining}:${secondsRemaining}`;

  return (
    <CustomFluidTypography>
      {({ width, fontSize, height }) => (
        <>
          <div className={classes.container}>
            <ClockInformation
              date={dateFromCountDown.locale(localeToUse)}
              isClock={false}
              showDate={showDate}
              showTimezone={showTimezone}
              timezone={timezoneToUse}
              width={width}
            />
            <div className={classes.clockLabel}>
              <Typography
                className={classes.timerLabel}
                data-hidden={equals(countdownHasEnded % 2, 1)}
                fontSize={fontSize}
              >
                {clockLabel}
              </Typography>
            </div>
            <div
              className={classes.clockHourMinuteSubLabel}
              style={{
                gap: fontSize / 2.2,
                gridTemplateColumns: `1fr auto 1fr`,
                top: (30 + height) / 2 + fontSize / 2.5
              }}
            >
              <Typography className={classes.icon} fontSize={fontSize / 2.8}>
                {t(displayFromDays ? labelDay : labelHour)}
              </Typography>
              <Typography
                className={classes.timezone}
                fontSize={fontSize / 2.8}
              >
                {t(displayFromDays ? labelHour : labelMinute)}
              </Typography>
              <Typography
                className={classes.date}
                fontSize={fontSize / 2.8}
                style={{ marginLeft: displayFromDays ? 0 : -(fontSize / 4.2) }}
              >
                {t(displayFromDays ? labelMinute : labelSecond)}
              </Typography>
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

export default memo(Timer, equals);
