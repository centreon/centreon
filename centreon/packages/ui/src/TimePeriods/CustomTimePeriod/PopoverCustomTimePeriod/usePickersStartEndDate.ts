import { Dispatch, SetStateAction, useEffect, useState } from 'react';

import dayjs from 'dayjs';
import { and, cond, equals, isNil } from 'ramda';

import { CustomTimePeriod, CustomTimePeriodProperty } from '../../models';

import { AcceptDateProps } from './models';

interface StartDate {
  setStart: Dispatch<SetStateAction<Date | null>>;
  start: Date | null;
}
interface EndDate {
  end: Date | null;
  setEnd: Dispatch<SetStateAction<Date | null>>;
}

export interface PickersStartEndDateModel {
  changeDate: (props: AcceptDateProps) => void;
  endDate: EndDate;
  error: boolean;
  startDate: StartDate;
}
interface Props {
  acceptDate: (props: AcceptDateProps) => void;
  customTimePeriod: CustomTimePeriod;
}

const usePickersStartEndDate = ({
  customTimePeriod,
  acceptDate
}: Props): PickersStartEndDateModel => {
  const [start, setStart] = useState<Date | null>(
    !isNil(customTimePeriod) ? customTimePeriod.start : null
  );
  const [end, setEnd] = useState<Date | null>(
    !isNil(customTimePeriod) ? customTimePeriod.end : null
  );

  const [error, setError] = useState(false);

  const isInvalidDate = ({ startDate, endDate }): boolean =>
    dayjs(startDate).isSameOrAfter(dayjs(endDate), 'minute');

  const changeDate = ({ property, date }): void => {
    const currentDate = customTimePeriod[property];
    cond([
      [equals(CustomTimePeriodProperty?.start), (): void => setStart(date)],
      [equals(CustomTimePeriodProperty?.end), (): void => setEnd(date)]
    ])(property);

    if (dayjs(date).isSame(dayjs(currentDate)) || !dayjs(date).isValid()) {
      return;
    }

    acceptDate({
      date,
      property
    });
  };

  useEffect(() => {
    if (
      and(
        dayjs(customTimePeriod.start).isSame(dayjs(start), 'minute'),
        dayjs(customTimePeriod.end).isSame(dayjs(end), 'minute')
      )
    ) {
      return;
    }
    setStart(customTimePeriod.start);
    setEnd(customTimePeriod.end);
  }, [customTimePeriod.start, customTimePeriod.end]);

  useEffect(() => {
    if (!end || !start) {
      return;
    }
    setError(isInvalidDate({ endDate: end, startDate: start }));
  }, [end, start]);

  return {
    changeDate,
    endDate: { end, setEnd },
    error,
    startDate: { setStart, start }
  };
};

export default usePickersStartEndDate;
