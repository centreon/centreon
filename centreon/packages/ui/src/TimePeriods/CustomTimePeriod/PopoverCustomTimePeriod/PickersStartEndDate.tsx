import { useAtomValue } from 'jotai';
import { makeStyles } from 'tss-react/mui';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';
import dayjs from 'dayjs';
import utc from 'dayjs/plugin/utc';
import timezone from 'dayjs/plugin/timezone';

import { AdapterDayjs } from '@mui/x-date-pickers/AdapterDayjs';
import { Typography } from '@mui/material';
import { LocalizationProvider } from '@mui/x-date-pickers';

import DateTimePickerInput from '../../DateTimePickerInput';
import {
  CustomTimePeriodProperty,
  DateTimePickerInputModel
} from '../../models';
import { errorTimePeriodAtom } from '../../timePeriodsAtoms';
import { useLocale } from '../../../utils';

import ErrorText from './ErrorText';
import { PickersData, PickersStartEndDateDirection } from './models';
import { PickersStartEndDateModel } from './usePickersStartEndDate';

dayjs.extend(utc);
dayjs.extend(timezone);

const useStyles = makeStyles()((theme) => ({
  error: {
    textAlign: 'center'
  },
  horizontalDirection: {
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(1),
    justifyItems: 'center',
    padding: 0
  },
  horizontalError: {
    textAlign: 'left'
  },
  row: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(1)
  },
  verticalDirection: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(1),
    justifyItems: 'center',
    padding: theme.spacing(1, 2)
  }
}));

interface PropsPickersDateWithLabel extends DateTimePickerInputModel {
  direction?: PickersStartEndDateDirection;
  label: string;
}

const PickerDateWithLabel = ({
  label,
  changeDate,
  maxDate,
  minDate,
  property,
  disabled,
  date,
  direction = PickersStartEndDateDirection.column
}: PropsPickersDateWithLabel): JSX.Element => {
  const { classes, cx } = useStyles();
  const { t } = useTranslation();
  const isRow = equals(direction, PickersStartEndDateDirection.row);

  return (
    <div aria-label={label} className={cx({ [classes.row]: isRow })}>
      <Typography component={isRow ? 'div' : 'p'}>{t(label)}</Typography>
      <DateTimePickerInput
        changeDate={changeDate}
        date={date}
        disabled={disabled}
        maxDate={maxDate}
        minDate={minDate}
        property={property}
      />
    </div>
  );
};

interface DisabledPicker {
  isDisabledEndPicker?: boolean;
  isDisabledStartPicker?: boolean;
}
type PickersDate = Pick<PickersData, 'rangeEndDate' | 'rangeStartDate'>;

interface Props extends PickersDate, PickersStartEndDateModel {
  direction?: PickersStartEndDateDirection;
  disabled?: DisabledPicker;
}

const PickersStartEndDate = ({
  startDate,
  endDate,
  disabled,
  changeDate,
  rangeStartDate,
  rangeEndDate,
  direction = PickersStartEndDateDirection.column
}: Props): JSX.Element => {
  const { classes, cx } = useStyles();

  const locale = useLocale();
  const error = useAtomValue(errorTimePeriodAtom);

  const maxStart = rangeStartDate?.max || endDate;
  const minStart = rangeStartDate?.min;
  const maxEnd = rangeEndDate?.max;
  const minEnd = rangeEndDate?.min || startDate;

  const styleContainer = equals(direction, PickersStartEndDateDirection.column)
    ? classes.verticalDirection
    : classes.horizontalDirection;

  const isHorizontalDirection = equals(
    direction,
    PickersStartEndDateDirection.row
  );

  return (
    <LocalizationProvider
      adapterLocale={locale.substring(0, 2)}
      dateAdapter={AdapterDayjs}
    >
      <div className={styleContainer}>
        <PickerDateWithLabel
          changeDate={changeDate}
          date={startDate}
          direction={direction}
          disabled={disabled?.isDisabledStartPicker}
          label="From"
          maxDate={maxStart || undefined}
          minDate={minStart}
          property={CustomTimePeriodProperty.start}
        />
        <PickerDateWithLabel
          changeDate={changeDate}
          date={endDate}
          direction={direction}
          disabled={disabled?.isDisabledEndPicker}
          label="to"
          maxDate={maxEnd}
          minDate={minEnd || undefined}
          property={CustomTimePeriodProperty.end}
        />
      </div>

      {error && (
        <ErrorText
          message="The end date must be greater than the start date"
          style={cx(classes.error, {
            [classes.horizontalError]: isHorizontalDirection
          })}
        />
      )}
    </LocalizationProvider>
  );
};

export default PickersStartEndDate;
