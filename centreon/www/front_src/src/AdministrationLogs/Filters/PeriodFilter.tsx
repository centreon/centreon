import { SelectField, SimpleCustomTimePeriod } from '@centreon/ui';

import dayjs from 'dayjs';
import { useAtom, useSetAtom } from 'jotai';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import { filtersAtom, pageAtom } from '../atoms';
import { Period } from '../models';
import {
  labelAllDates,
  labelCustomPeriod,
  labelLast7Days,
  labelLast24Hours,
  labelLast30Days,
  labelLastHour,
  labelPeriod
} from '../translatedLabels';
import { presetDurations } from '../useActivityLogsQueryParameters';

const options = [
  { id: Period.All, name: labelAllDates },
  { id: Period.LastHour, name: labelLastHour },
  { id: Period.Last24Hours, name: labelLast24Hours },
  { id: Period.Last7Days, name: labelLast7Days },
  { id: Period.Last30Days, name: labelLast30Days },
  { id: Period.Custom, name: labelCustomPeriod }
];

const toMinute = (date: Date): Date => dayjs(date).startOf('minute').toDate();

// The custom range starts from the preset it replaces ("All dates": the last 24 hours)
const getInitialCustomRange = (period: Period): { from: Date; to: Date } => {
  const [amount, unit] = presetDurations[period] ?? [1, 'day'];
  const now = new Date();

  return {
    from: toMinute(dayjs(now).subtract(amount, unit).toDate()),
    to: toMinute(now)
  };
};

// A preset selector; "Custom period" shows the start and end pickers next to it,
// applied as soon as a date changes (as in the BA Timeline)
const PeriodFilter = (): JSX.Element => {
  const { t } = useTranslation();

  const [filters, setFilters] = useAtom(filtersAtom);
  const setPage = useSetAtom(pageAtom);

  const translatedOptions = options.map(({ id, name }) => ({
    id,
    name: t(name)
  }));

  const changePeriod = (event: { target: { value: unknown } }): void => {
    const period = event.target.value as Period;

    if (equals(period, filters.period)) {
      return;
    }

    setPage(1);
    setFilters((current) => ({
      ...current,
      ...(equals(period, Period.Custom)
        ? getInitialCustomRange(current.period)
        : { from: null, to: null }),
      period
    }));
  };

  const changeCustomDate = ({
    date,
    property
  }: {
    date: Date;
    property: string;
  }): void => {
    const isStart = equals(property, 'start');
    const from = isStart ? toMinute(date) : filters.from;
    const to = isStart ? filters.to : toMinute(date);

    if (from && to && !dayjs(to).isAfter(from)) {
      return;
    }

    setPage(1);
    setFilters((current) => ({ ...current, from, to }));
  };

  const isCustom = equals(filters.period, Period.Custom);

  return (
    <div className="flex flex-row items-center gap-2">
      <SelectField
        ariaLabel={t(labelPeriod)}
        className="min-w-[180px]"
        dataTestId={labelPeriod}
        onChange={changePeriod}
        options={translatedOptions}
        selectedOptionId={filters.period}
        size="small"
      />
      {isCustom && filters.from && filters.to && (
        <SimpleCustomTimePeriod
          changeDate={changeCustomDate}
          className="p-0"
          endDate={filters.to}
          rangeEndDate={{ max: new Date(), min: filters.from }}
          rangeStartDate={{ max: filters.to }}
          startDate={filters.from}
        />
      )}
    </div>
  );
};

export default PeriodFilter;
