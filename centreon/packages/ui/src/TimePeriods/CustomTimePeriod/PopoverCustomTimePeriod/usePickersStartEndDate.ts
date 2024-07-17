import { useEffect, useState } from 'react';

import dayjs from 'dayjs';
import { useSetAtom } from 'jotai';
import { and, cond, equals, isNil } from 'ramda';

import { isInvalidDate } from '../../helpers';
import { CustomTimePeriod, CustomTimePeriodProperty } from '../../models';
import { errorTimePeriodAtom } from '../../timePeriodsAtoms';

import { AcceptDateProps } from './models';

export interface PickersStartEndDateModel {
  changeDate: (props: AcceptDateProps) => void;
  endDate: Date | null;
  startDate: Date | null;
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

  const setError = useSetAtom(errorTimePeriodAtom);

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
    endDate: end,
    startDate: start
  };
};

export default usePickersStartEndDate;
