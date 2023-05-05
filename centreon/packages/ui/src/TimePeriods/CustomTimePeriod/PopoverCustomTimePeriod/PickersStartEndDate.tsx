import { useAtomValue } from 'jotai';
import { makeStyles } from 'tss-react/mui';
import { equals } from 'ramda';

import { Typography } from '@mui/material';
import { LocalizationProvider } from '@mui/x-date-pickers';

import { userAtom } from '@centreon/ui-context';

import DateTimePickerInput from '../../DateTimePickerInput';
import {
  CustomTimePeriodProperty,
  DateTimePickerInputModel
} from '../../models';
import useDateTimePickerAdapter from '../../useDateTimePickerAdapter';
import { errorTimePeriodAtom } from '../../timePeriodsAtoms';

import ErrorText from './ErrorText';
import { PickersData, PickersStartEndDateDirection } from './models';
import { PickersStartEndDateModel } from './usePickersStartEndDate';

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
  onClosePicker,
  disabled,
  date,
  setDate,
  direction = PickersStartEndDateDirection.column
}: PropsPickersDateWithLabel): JSX.Element => {
  const { classes, cx } = useStyles();
  const isRow = equals(direction, PickersStartEndDateDirection.row);

  return (
    <div aria-label={label} className={cx({ [classes.row]: isRow })}>
      <Typography component={isRow ? 'div' : 'p'}>{label}</Typography>
      <DateTimePickerInput
        changeDate={changeDate}
        date={date}
        disabled={disabled}
        maxDate={maxDate}
        minDate={minDate}
        property={property}
        setDate={setDate}
        onClosePicker={onClosePicker}
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
  const { Adapter } = useDateTimePickerAdapter();

  const { locale } = useAtomValue(userAtom);
  const error = useAtomValue(errorTimePeriodAtom);

  const { start, setStart } = startDate;
  const { end, setEnd } = endDate;

  const maxStart = rangeStartDate?.max;
  const minStart = rangeStartDate?.min;
  const maxEnd = rangeEndDate?.max;
  const minEnd = rangeEndDate?.min;

  const styleContainer = equals(direction, PickersStartEndDateDirection.column)
    ? classes.verticalDirection
    : classes.horizontalDirection;

  const isHorizontalDirection = equals(
    direction,
    PickersStartEndDateDirection.row
  );

  return (
    <LocalizationProvider dateAdapter={Adapter} locale={locale.substring(0, 2)}>
      <div className={styleContainer}>
        <PickerDateWithLabel
          changeDate={changeDate}
          date={start}
          direction={direction}
          disabled={disabled?.isDisabledStartPicker}
          label="From"
          maxDate={maxStart}
          minDate={minStart}
          property={CustomTimePeriodProperty.start}
          setDate={setStart}
        />
        <PickerDateWithLabel
          changeDate={changeDate}
          date={end}
          direction={direction}
          disabled={disabled?.isDisabledEndPicker}
          label="To"
          maxDate={maxEnd}
          minDate={minEnd}
          property={CustomTimePeriodProperty.end}
          setDate={setEnd}
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
