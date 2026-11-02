import AccessTimeIcon from '@mui/icons-material/AccessTime';
import ArrowDropDownIcon from '@mui/icons-material/ArrowDropDown';
import CheckIcon from '@mui/icons-material/Check';
import {
  Divider,
  ListItemIcon,
  ListItemText,
  MenuItem,
  MenuList,
  Popover,
  Typography
} from '@mui/material';

import {
  PickersStartEndDateDirection,
  SimpleCustomTimePeriod,
  TextField,
  useLocaleDateTimeFormat
} from '@centreon/ui';
import { Button } from '@centreon/ui/components';

import dayjs from 'dayjs';
import { useAtom, useSetAtom } from 'jotai';
import { equals, isNil } from 'ramda';
import { type MouseEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { filtersAtom, pageAtom } from '../atoms';
import { Period } from '../models';
import {
  labelAllDates,
  labelApply,
  labelCustomPeriod,
  labelLast7Days,
  labelLast24Hours,
  labelLast30Days,
  labelLastHour,
  labelPeriod
} from '../translatedLabels';

const presetLabels: Record<Exclude<Period, Period.Custom>, string> = {
  [Period.All]: labelAllDates,
  [Period.LastHour]: labelLastHour,
  [Period.Last24Hours]: labelLast24Hours,
  [Period.Last7Days]: labelLast7Days,
  [Period.Last30Days]: labelLast30Days
};

interface DateRange {
  from: Date;
  to: Date;
}

const ClockAdornment = (): JSX.Element => (
  <AccessTimeIcon className="text-text-secondary" fontSize="small" />
);

// A read-only field with the same look and height as the search bar. It shows
// the preset or the custom range, and opens the presets and the custom pickers
const PeriodFilter = (): JSX.Element => {
  const { t } = useTranslation();
  const { format } = useLocaleDateTimeFormat();

  const [filters, setFilters] = useAtom(filtersAtom);
  const setPage = useSetAtom(pageAtom);

  const [anchorEl, setAnchorEl] = useState<HTMLElement | null>(null);
  const [customRange, setCustomRange] = useState<DateRange | null>(null);

  const formatShortDate = (date: Date): string =>
    format({ date, formatString: 'L LT' });

  const fieldValue =
    equals(filters.period, Period.Custom) && filters.from && filters.to
      ? `${formatShortDate(filters.from)} → ${formatShortDate(filters.to)}`
      : t(presetLabels[filters.period as Exclude<Period, Period.Custom>]);

  const open = (event: MouseEvent<HTMLElement>): void => {
    setCustomRange({
      from: filters.from ?? dayjs().subtract(1, 'day').toDate(),
      to: filters.to ?? new Date()
    });
    setAnchorEl(event.currentTarget);
  };

  const close = (): void => setAnchorEl(null);

  const selectPreset = (period: Period): void => {
    setPage(1);
    setFilters((current) => ({ ...current, from: null, period, to: null }));
    close();
  };

  const changeCustomDate = ({
    date,
    property
  }: {
    date: Date;
    property: string;
  }): void =>
    setCustomRange((current) =>
      current
        ? { ...current, [equals(property, 'start') ? 'from' : 'to']: date }
        : current
    );

  const applyCustomRange = (): void => {
    if (isNil(customRange)) {
      return;
    }

    setPage(1);
    setFilters((current) => ({
      ...current,
      ...customRange,
      period: Period.Custom
    }));
    close();
  };

  return (
    <>
      <TextField
        ariaLabel={t(labelPeriod)}
        className="w-[300px] max-w-full cursor-pointer"
        dataTestId={labelPeriod}
        EndAdornment={<ArrowDropDownIcon className="text-text-secondary" />}
        onClick={open}
        StartAdornment={ClockAdornment}
        textFieldSlotsAndSlotProps={{
          slotProps: {
            htmlInput: { className: 'cursor-pointer', readOnly: true }
          }
        }}
        title={t(labelPeriod)}
        value={fieldValue}
      />
      <Popover
        anchorEl={anchorEl}
        anchorOrigin={{ horizontal: 'left', vertical: 'bottom' }}
        onClose={close}
        open={!isNil(anchorEl)}
        slotProps={{ paper: { className: 'mt-2 w-[300px]' } }}
      >
        <MenuList dense>
          {Object.entries(presetLabels).map(([period, label]) => (
            <MenuItem
              key={period}
              onClick={() => selectPreset(period as Period)}
              selected={equals(filters.period, period)}
            >
              <ListItemIcon>
                {equals(filters.period, period) && (
                  <CheckIcon fontSize="small" />
                )}
              </ListItemIcon>
              <ListItemText>{t(label)}</ListItemText>
            </MenuItem>
          ))}
        </MenuList>
        <Divider />
        <div className="flex flex-col gap-2 p-3">
          <Typography className="font-bold" variant="body2">
            {t(labelCustomPeriod)}
          </Typography>
          {customRange && (
            <SimpleCustomTimePeriod
              changeDate={changeCustomDate}
              className="p-0"
              direction={PickersStartEndDateDirection.column}
              endDate={customRange.to}
              startDate={customRange.from}
            />
          )}
          <div className="flex justify-end">
            <Button onClick={applyCustomRange} size="small">
              {t(labelApply)}
            </Button>
          </div>
        </div>
      </Popover>
    </>
  );
};

export default PeriodFilter;
