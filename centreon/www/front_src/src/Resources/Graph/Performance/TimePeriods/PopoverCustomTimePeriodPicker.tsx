import { Dispatch, SetStateAction, useEffect, useState } from 'react';

import dayjs from 'dayjs';
import { useAtomValue } from 'jotai/utils';
import { and, cond, equals } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import {
  FormHelperText,
  Popover,
  PopoverOrigin,
  PopoverReference,
  Typography
} from '@mui/material';
import { LocalizationProvider } from '@mui/x-date-pickers';

import { userAtom } from '@centreon/ui-context';

import {
  CustomTimePeriod,
  CustomTimePeriodProperty
} from '../../../Details/tabs/Graph/models';
import {
  labelEndDate,
  labelEndDateGreaterThanStartDate,
  labelFrom,
  labelStartDate,
  labelTo
} from '../../../translatedLabels';
import useDateTimePickerAdapter from '../../../useDateTimePickerAdapter';

import DateTimePickerInput from './DateTimePickerInput';
import { AnchorReference } from './models';

const useStyles = makeStyles()((theme) => ({
  error: {
    textAlign: 'center'
  },
  paper: {
    '& .MuiPopover-paper': {
      minWidth: 250,
      padding: theme.spacing(1)
    }
  }
}));

interface AcceptDateProps {
  date: Date;
  property: CustomTimePeriodProperty;
}

interface Props {
  acceptDate: (props: AcceptDateProps) => void;
  anchorOrigin?: PopoverOrigin;
  anchorReference?: PopoverReference;
  classNameError?: string;
  classNamePaper?: string;
  classNamePicker?: string;
  customTimePeriod: CustomTimePeriod;
  disabledPickerEndInput?: boolean;
  disabledPickerStartInput?: boolean;
  getIsErrorDatePicker?: (value: boolean) => void;
  maxDatePickerEndInput?: Date | dayjs.Dayjs;
  maxDatePickerStartInput?: Date;
  minDatePickerEndInput?: Date;
  minDatePickerStartInput?: Date;
  onClose: () => void;
  onCloseEndPicker?: (isClosed: boolean) => void;
  onCloseStartPicker?: (isClosed: boolean) => void;
  open: boolean;
  pickerEndWithoutInitialValue?: boolean;
  pickerStartWithoutInitialValue?: boolean;
  reference?: AnchorReference;
  renderBody?: JSX.Element;
  renderFooter?: JSX.Element;
  renderTitle?: JSX.Element;
  setPickerEndWithoutInitialValue?: Dispatch<SetStateAction<boolean>>;
  setPickerStartWithoutInitialValue?: Dispatch<SetStateAction<boolean>>;
  transformOrigin?: PopoverOrigin;
}

const PopoverCustomTimePeriodPickers = ({
  reference,
  anchorReference = 'none',
  anchorOrigin = {
    horizontal: 'center',
    vertical: 'top'
  },
  transformOrigin = {
    horizontal: 'center',
    vertical: 'top'
  },
  open,
  classNamePaper,
  classNamePicker,
  customTimePeriod,
  acceptDate,
  renderTitle,
  renderBody,
  renderFooter,
  pickerStartWithoutInitialValue,
  pickerEndWithoutInitialValue,
  setPickerStartWithoutInitialValue,
  setPickerEndWithoutInitialValue,
  maxDatePickerStartInput = customTimePeriod?.end,
  minDatePickerStartInput,
  minDatePickerEndInput = customTimePeriod?.start,
  maxDatePickerEndInput,
  classNameError,
  getIsErrorDatePicker,
  onCloseStartPicker,
  onCloseEndPicker,
  disabledPickerEndInput,
  disabledPickerStartInput,
  onClose
}: Props): JSX.Element => {
  const { classes, cx } = useStyles();
  const { t } = useTranslation();
  const [start, setStart] = useState<Date>(customTimePeriod.start);
  const [error, setError] = useState(false);
  const [end, setEnd] = useState<Date>(customTimePeriod.end);

  const { locale } = useAtomValue(userAtom);
  const { Adapter } = useDateTimePickerAdapter();

  const isInvalidDate = ({ startDate, endDate }): boolean =>
    dayjs(startDate).isSameOrAfter(dayjs(endDate), 'minute');

  const changeDate = ({ property, date }): void => {
    const currentDate = customTimePeriod[property];

    cond([
      [
        (): boolean => equals(CustomTimePeriodProperty.start, property),
        (): void => setStart(date)
      ],
      [
        (): boolean => equals(CustomTimePeriodProperty.end, property),
        (): void => setEnd(date)
      ]
    ])();

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
    if (pickerStartWithoutInitialValue || pickerEndWithoutInitialValue) {
      return;
    }
    if (!end || !start) {
      return;
    }
    setError(isInvalidDate({ endDate: end, startDate: start }));
  }, [
    end,
    start,
    pickerStartWithoutInitialValue,
    pickerEndWithoutInitialValue
  ]);

  useEffect(() => {
    getIsErrorDatePicker?.(error);
  }, [error]);

  return (
    <Popover
      anchorEl={reference?.anchorEl}
      anchorOrigin={anchorOrigin}
      anchorPosition={reference?.anchorPosition}
      anchorReference={anchorReference}
      className={cx(classes.paper)}
      open={open}
      transformOrigin={transformOrigin}
      onClose={onClose}
    >
      <div className={classNamePaper} data-testid="popover">
        {renderTitle}
        <LocalizationProvider
          dateAdapter={Adapter}
          locale={locale.substring(0, 2)}
        >
          <div className={classNamePicker}>
            <div>
              <Typography>{t(labelFrom)}</Typography>
              <div aria-label={t(labelStartDate)}>
                <DateTimePickerInput
                  changeDate={changeDate}
                  date={start}
                  disabled={disabledPickerStartInput}
                  maxDate={maxDatePickerStartInput}
                  minDate={minDatePickerStartInput}
                  property={CustomTimePeriodProperty.start}
                  setDate={setStart}
                  setWithoutInitialValue={setPickerStartWithoutInitialValue}
                  withoutInitialValue={pickerStartWithoutInitialValue}
                  onClosePicker={onCloseStartPicker}
                />
              </div>
            </div>
            <div>
              <Typography>{t(labelTo)}</Typography>
              <div aria-label={t(labelEndDate)}>
                <DateTimePickerInput
                  changeDate={changeDate}
                  date={end}
                  disabled={disabledPickerEndInput}
                  maxDate={maxDatePickerEndInput}
                  minDate={minDatePickerEndInput}
                  property={CustomTimePeriodProperty.end}
                  setDate={setEnd}
                  setWithoutInitialValue={setPickerEndWithoutInitialValue}
                  withoutInitialValue={pickerEndWithoutInitialValue}
                  onClosePicker={onCloseEndPicker}
                />
              </div>
            </div>
          </div>
          {error && (
            <FormHelperText error className={cx(classes.error, classNameError)}>
              {t(labelEndDateGreaterThanStartDate)}
            </FormHelperText>
          )}
        </LocalizationProvider>
        {renderBody}
        {renderFooter}
      </div>
    </Popover>
  );
};

export default PopoverCustomTimePeriodPickers;
