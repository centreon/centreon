import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

import dayjs, { Dayjs } from 'dayjs';
import { equals, inc } from 'ramda';

import { useGetLocaleAndTimezone } from './useGetLocaleAndTimezone';

interface UseTimerState {
  countdownHasEnded: number;
  date: Dayjs;
  dateFromCountDown: Dayjs;
  locale: string;
  timezone: string;
}

export const useTimer = ({ locale, timezone, countdown }): UseTimerState => {
  const [date, setDate] = useState(dayjs());
  const [countdownHasEnded, setCountdownHasEnded] = useState(0);
  const timer5SecondsRef = useRef<NodeJS.Timeout | undefined>();
  const timer1SecondRef = useRef<NodeJS.Timeout | undefined>();
  const timerCoundownHasEnded = useRef<NodeJS.Timeout | undefined>();

  const { timezone: timezoneToUse, locale: localeToUse } =
    useGetLocaleAndTimezone({
      locale,
      timezone
    });

  const isCountdownValid = useMemo(
    () => countdown && countdown > dayjs().tz(timezoneToUse).valueOf(),
    [countdown, timezoneToUse]
  );
  const dateFromCountDown = useMemo(
    () => dayjs(countdown || 0).tz(timezoneToUse),
    [countdown, timezoneToUse]
  );

  const updateDate = useCallback(() => setDate(dayjs()), []);

  const timer1SecondCallback = (): void => {
    updateDate();
    const timeRemaining = dayjs.duration(
      dateFromCountDown.diff(dayjs().tz(timezoneToUse))
    );

    if (timeRemaining.milliseconds() <= 0) {
      setCountdownHasEnded(1);
      clearInterval(timer1SecondRef.current);
      timerCoundownHasEnded.current = setInterval(() => {
        setCountdownHasEnded((currentCountdownHasEnded) => {
          if (currentCountdownHasEnded > 10) {
            clearInterval(timerCoundownHasEnded.current);

            return 0;
          }

          return inc(currentCountdownHasEnded);
        });
      }, 500);
    }
  };

  useEffect(() => {
    setCountdownHasEnded(0);
    clearInterval(timerCoundownHasEnded.current);
    if (!isCountdownValid) {
      clearInterval(timer1SecondRef.current);
      clearInterval(timer5SecondsRef.current);

      return () => {
        clearInterval(timer5SecondsRef.current);
        clearInterval(timer1SecondRef.current);
      };
    }

    const diff = dateFromCountDown.diff(date);
    const duration = dayjs.duration(diff);

    const daysRemaining = duration.days();

    if (daysRemaining < 1) {
      updateDate();
      timer1SecondRef.current = setInterval(timer1SecondCallback, 1_000);

      return () => {
        clearInterval(timer5SecondsRef.current);
        clearInterval(timer1SecondRef.current);
      };
    }

    timer5SecondsRef.current = setInterval(() => {
      updateDate();
      const timeRemaining = dayjs.duration(
        dateFromCountDown.diff(dayjs().tz(timezoneToUse))
      );
      const daysRemainingInterval = timeRemaining.days();
      const secondsRemainingInterval = timeRemaining.seconds();

      if (equals(daysRemainingInterval, 1) && secondsRemainingInterval <= 5) {
        timer1SecondRef.current = setInterval(timer1SecondCallback, 1_000);
        clearInterval(timer5SecondsRef.current);
      }
    }, 5_000);

    return () => {
      clearInterval(timer5SecondsRef.current);
      clearInterval(timer1SecondRef.current);
    };
  }, [isCountdownValid, dateFromCountDown]);

  return {
    countdownHasEnded,
    date: date.tz(timezoneToUse),
    dateFromCountDown,
    locale: localeToUse,
    timezone: timezoneToUse
  };
};
