import { useEffect, useState } from 'react';

import dayjs from 'dayjs';
import { useAtomValue } from 'jotai/utils';
import { and, cond, equals, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { FormHelperText, Popover, Typography } from '@mui/material';
import { LocalizationProvider } from '@mui/x-date-pickers';

import { userAtom } from '@centreon/ui-context';

import { CustomTimePeriodProperty } from '../../../Details/tabs/Graph/models';
import {
  labelEndDate,
  labelEndDateGreaterThanStartDate,
  labelFrom,
  labelStartDate,
  labelTo
} from '../../../translatedLabels';
import useDateTimePickerAdapter from '../../../useDateTimePickerAdapter';

import DateTimePickerInput from './DateTimePickerInput';
import {
  CustomStyle,
  OriginHorizontalEnum,
  OriginVerticalEnum,
  PickersData,
  PopoverData,
  anchorReferenceEnum
} from './models';

const useStyles = makeStyles()((theme) => ({
  paper: {
    '& .MuiPopover-paper': {
      minWidth: 250,
      padding: theme.spacing(1)
    }
  }
}));

const defaultPickersData = {
  disabledPickerEndInput: false,
  disabledPickerStartInput: false,
  getIsErrorDatePicker: (): void => undefined,
  maxDatePickerEndInput: undefined,
  maxDatePickerStartInput: undefined,
  minDatePickerEndInput: undefined,
  minDatePickerStartInput: undefined,
  onCloseEndPicker: (): void => undefined,
  onCloseStartPicker: (): void => undefined
};

const defaultPopoverData = {
  anchorEl: undefined,
  anchorOrigin: {
    horizontal: OriginHorizontalEnum.center,
    vertical: OriginVerticalEnum.top
  },
  anchorPosition: undefined,
  anchorReference: anchorReferenceEnum.none,
  onClose: (): void => undefined,
  transformOrigin: {
    horizontal: OriginHorizontalEnum.center,
    vertical: OriginVerticalEnum.top
  }
};

const defaultCustomStyle = {
  classNameError: undefined,
  classNamePaper: undefined,
  classNamePicker: undefined
};

export interface Props {
  customStyle?: CustomStyle;
  pickersData: PickersData;
  popoverData: PopoverData;
  renderBody?: JSX.Element;
  renderFooter?: JSX.Element;
  renderTitle?: JSX.Element;
}

const PopoverCustomTimePeriodPickers = ({
  popoverData,
  customStyle = defaultCustomStyle,
  renderTitle,
  renderBody,
  renderFooter,
  pickersData
}: Props): JSX.Element => {
  const { classes, cx } = useStyles();
  const { t } = useTranslation();
  const { classNamePaper, classNameError, classNamePicker } = customStyle;
  const {
    open,
    onClose,
    anchorOrigin,
    transformOrigin,
    anchorPosition,
    anchorReference,
    anchorEl
  } = {
    ...defaultPopoverData,
    ...popoverData
  };

  const {
    acceptDate,
    customTimePeriod,
    disabledPickerEndInput,
    disabledPickerStartInput,
    getIsErrorDatePicker,
    maxDatePickerEndInput,
    maxDatePickerStartInput,
    minDatePickerEndInput,
    minDatePickerStartInput,
    onCloseEndPicker,
    onCloseStartPicker
  } = { ...defaultPickersData, ...pickersData };

  const [start, setStart] = useState<Date | null>(
    !isNil(customTimePeriod) ? customTimePeriod.start : null
  );
  const [error, setError] = useState(false);
  const [end, setEnd] = useState<Date | null>(
    !isNil(customTimePeriod) ? customTimePeriod.end : null
  );
  const { locale } = useAtomValue(userAtom);
  const { Adapter } = useDateTimePickerAdapter();

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

  useEffect(() => {
    getIsErrorDatePicker?.(error);
  }, [error]);

  return (
    <Popover
      anchorEl={anchorEl}
      anchorOrigin={anchorOrigin}
      anchorPosition={anchorPosition}
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
            <Typography>{t(labelFrom)}</Typography>
            <div aria-label={t(labelStartDate) as string}>
              <DateTimePickerInput
                changeDate={changeDate}
                date={start}
                disabled={disabledPickerStartInput}
                maxDate={maxDatePickerStartInput}
                minDate={minDatePickerStartInput}
                property={CustomTimePeriodProperty.start}
                setDate={setStart}
                onClosePicker={onCloseStartPicker}
              />
            </div>
            <Typography>{t(labelTo)}</Typography>
            <div aria-label={t(labelEndDate) as string}>
              <DateTimePickerInput
                changeDate={changeDate}
                date={end}
                disabled={disabledPickerEndInput}
                maxDate={maxDatePickerEndInput}
                minDate={minDatePickerEndInput}
                property={CustomTimePeriodProperty.end}
                setDate={setEnd}
                onClosePicker={onCloseEndPicker}
              />
            </div>
          </div>
          {error && (
            <FormHelperText error className={classNameError}>
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
