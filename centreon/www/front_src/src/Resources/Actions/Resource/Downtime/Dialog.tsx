<<<<<<< HEAD
import { useState } from 'react';

import { useTranslation } from 'react-i18next';
import dayjs from 'dayjs';
import { useAtomValue } from 'jotai/utils';
import { FormikErrors, FormikHandlers, FormikValues } from 'formik';
import { isNil } from 'ramda';

import { LocalizationProvider, DateTimePicker } from '@mui/lab';
=======
/* eslint-disable class-methods-use-this */
import * as React from 'react';

import { useTranslation } from 'react-i18next';
import { not } from 'ramda';
import { FormikErrors, FormikHandlers, FormikValues } from 'formik';

>>>>>>> centreon/dev-21.10.x
import {
  Checkbox,
  FormControlLabel,
  FormHelperText,
<<<<<<< HEAD
  Alert,
  TextFieldProps,
  Stack,
} from '@mui/material';
import { Box } from '@mui/system';

import { Dialog, TextField, SelectField } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import {
  labelCancel,
  labelEndTime,
=======
  Grid,
} from '@material-ui/core';
import {
  MuiPickersUtilsProvider,
  KeyboardTimePicker,
  KeyboardDatePicker,
  DatePickerProps,
  TimePickerProps,
} from '@material-ui/pickers';
import { Alert } from '@material-ui/lab';

import { Dialog, TextField, SelectField } from '@centreon/ui';
import { useUserContext } from '@centreon/ui-context';

import {
  labelCancel,
  labelEndDate,
  labelEndTime,
  labelStartDate,
  labelStartTime,
  labelChangeEndDate,
  labelChangeEndTime,
  labelChangeStartDate,
  labelChangeStartTime,
>>>>>>> centreon/dev-21.10.x
  labelComment,
  labelDowntime,
  labelDuration,
  labelFixed,
<<<<<<< HEAD
=======
  labelFrom,
>>>>>>> centreon/dev-21.10.x
  labelHours,
  labelMinutes,
  labelSeconds,
  labelSetDowntime,
  labelSetDowntimeOnServices,
  labelTo,
<<<<<<< HEAD
  labelStartTime,
=======
>>>>>>> centreon/dev-21.10.x
} from '../../../translatedLabels';
import { Resource } from '../../../models';
import useAclQuery from '../aclQuery';
import useDateTimePickerAdapter from '../../../useDateTimePickerAdapter';

import { DowntimeFormValues } from '.';

const maxEndDate = new Date('2100-01-01');

interface Props extends Pick<FormikHandlers, 'handleChange'> {
  canConfirm: boolean;
  errors?: FormikErrors<DowntimeFormValues>;
  handleChange;
  onCancel: () => void;
  onConfirm: () => Promise<unknown>;
  resources: Array<Resource>;
  setFieldValue;
  submitting: boolean;
  values: FormikValues;
}

<<<<<<< HEAD
const renderDateTimePickerEndAdornment = (InputProps) => (): JSX.Element =>
  <div>{InputProps?.endAdornment}</div>;

const renderDateTimePickerTextField =
  (ariaLabel: string) =>
  ({ inputRef, inputProps, InputProps }: TextFieldProps): JSX.Element => {
    return (
      <TextField
        EndAdornment={renderDateTimePickerEndAdornment(InputProps)}
        inputProps={{
          ...inputProps,
          'aria-label': ariaLabel,
          ref: inputRef,
          style: { padding: 8 },
        }}
      />
    );
  };
=======
const pickerCommonProps = {
  InputProps: {
    disableUnderline: true,
  },
  TextFieldComponent: TextField,
  inputVariant: 'filled',
  margin: 'none',
  variant: 'inline',
};

const datePickerProps = {
  ...pickerCommonProps,
  disableToolbar: true,
  format: 'L',
} as Omit<DatePickerProps, 'onChange' | 'value'>;

const timePickerProps = {
  ...pickerCommonProps,
  format: 'LT',
} as Omit<TimePickerProps, 'onChange' | 'value'>;
>>>>>>> centreon/dev-21.10.x

const DialogDowntime = ({
  resources,
  canConfirm,
  onCancel,
  onConfirm,
  errors,
  values,
  submitting,
  handleChange,
  setFieldValue,
}: Props): JSX.Element => {
  const { t } = useTranslation();
<<<<<<< HEAD

  const { getDowntimeDeniedTypeAlert, canDowntimeServices } = useAclQuery();
  const [isPickerOpened, setIsPickerOpened] = useState(false);

  const { locale } = useAtomValue(userAtom);

  const {
    Adapter,
    getDestinationAndConfiguredTimezoneOffset,
    formatKeyboardValue,
  } = useDateTimePickerAdapter();
=======
  const { locale } = useUserContext();
  const { getDowntimeDeniedTypeAlert, canDowntimeServices } = useAclQuery();
  const { Adapter, isMeridianFormat } = useDateTimePickerAdapter();
>>>>>>> centreon/dev-21.10.x

  const open = resources.length > 0;

  const hasHosts = resources.find((resource) => resource.type === 'host');

  const changeDate =
    (field) =>
    (value): void => {
      setFieldValue(field, value);
    };

  const deniedTypeAlert = getDowntimeDeniedTypeAlert(resources);

<<<<<<< HEAD
  const changeTime =
    (field) =>
    (newValue: dayjs.Dayjs | null, keyBoardValue: string | undefined): void => {
      const value = isPickerOpened
        ? dayjs(newValue).toDate()
        : dayjs(formatKeyboardValue(keyBoardValue))
            .add(
              dayjs.duration({
                hours: getDestinationAndConfiguredTimezoneOffset(),
              }),
            )
            .toDate();

      changeDate(field)(value);
    };

=======
>>>>>>> centreon/dev-21.10.x
  return (
    <Dialog
      confirmDisabled={!canConfirm}
      labelCancel={t(labelCancel)}
      labelConfirm={t(labelSetDowntime)}
      labelTitle={t(labelDowntime)}
      open={open}
      submitting={submitting}
      onCancel={onCancel}
      onClose={onCancel}
      onConfirm={onConfirm}
    >
      {deniedTypeAlert && <Alert severity="warning">{deniedTypeAlert}</Alert>}
<<<<<<< HEAD
      <LocalizationProvider
        dateAdapter={Adapter}
        locale={locale.substring(0, 2)}
      >
        <Stack spacing={1}>
          <Box
            alignItems="center"
            display="grid"
            gap={1}
            gridTemplateColumns="1fr auto 1fr"
          >
            <DateTimePicker<dayjs.Dayjs>
              maxDate={dayjs(maxEndDate)}
              renderInput={renderDateTimePickerTextField(t(labelStartTime))}
              value={values.startTime}
              onChange={changeTime('startTime')}
              onClose={(): void => setIsPickerOpened(false)}
              onOpen={(): void => setIsPickerOpened(true)}
            />
            <FormHelperText>{t(labelTo)}</FormHelperText>
            <DateTimePicker<dayjs.Dayjs>
              renderInput={renderDateTimePickerTextField(t(labelEndTime))}
              value={values.endTime}
              onChange={changeTime('endTime')}
              onClose={(): void => setIsPickerOpened(false)}
              onOpen={(): void => setIsPickerOpened(true)}
            />
            {isNil(errors?.startTime) ? (
              <div />
            ) : (
              <FormHelperText error>
                {errors?.startTime as string}
              </FormHelperText>
            )}
            <div />
            {isNil(errors?.endTime) ? (
              <div />
            ) : (
              <FormHelperText error>{errors?.endTime as string}</FormHelperText>
            )}
          </Box>

          <Stack>
            <FormHelperText>{t(labelDuration)}</FormHelperText>

            <Stack alignItems="center" direction="row" spacing={1}>
              <TextField
                ariaLabel={t(labelDuration)}
                disabled={values.fixed}
                error={errors?.duration?.value}
                type="number"
                value={values.duration.value}
                onChange={handleChange('duration.value')}
              />
              <SelectField
                disabled={values.fixed}
                options={[
                  {
                    id: 'seconds',
                    name: t(labelSeconds),
                  },
                  {
                    id: 'minutes',
                    name: t(labelMinutes),
                  },
                  {
                    id: 'hours',
                    name: t(labelHours),
                  },
                ]}
                selectedOptionId={values.duration.unit}
                onChange={handleChange('duration.unit')}
              />
              <FormControlLabel
                control={
                  <Checkbox
                    checked={values.fixed}
                    color="primary"
                    inputProps={{ 'aria-label': t(labelFixed) }}
                    size="small"
                    onChange={handleChange('fixed')}
                  />
                }
                label={t(labelFixed) as string}
              />
            </Stack>
          </Stack>
          <TextField
            fullWidth
            multiline
            error={errors?.comment}
            label={t(labelComment)}
            rows={3}
            value={values.comment}
            onChange={handleChange('comment')}
          />
          {hasHosts && (
            <FormControlLabel
              control={
                <Checkbox
                  checked={
                    canDowntimeServices() && values.isDowntimeWithServices
                  }
                  color="primary"
                  disabled={!canDowntimeServices()}
                  inputProps={{ 'aria-label': labelSetDowntimeOnServices }}
                  size="small"
                  onChange={handleChange('isDowntimeWithServices')}
                />
              }
              label={t(labelSetDowntimeOnServices) as string}
            />
          )}
        </Stack>
      </LocalizationProvider>
=======
      <MuiPickersUtilsProvider locale={locale.substring(0, 2)} utils={Adapter}>
        <Grid container direction="column" spacing={1}>
          <Grid item>
            <FormHelperText>{t(labelFrom)}</FormHelperText>
            <Grid container direction="row" spacing={1}>
              <Grid item style={{ width: 240 }}>
                <KeyboardDatePicker
                  KeyboardButtonProps={{
                    'aria-label': t(labelChangeStartDate),
                  }}
                  aria-label={t(labelStartDate)}
                  error={errors?.dateStart !== undefined}
                  helperText={errors?.dateStart}
                  inputMode="text"
                  maxDate={maxEndDate}
                  value={values.dateStart}
                  onChange={changeDate('dateStart')}
                  {...datePickerProps}
                />
              </Grid>
              <Grid item style={{ width: 200 }}>
                <KeyboardTimePicker
                  KeyboardButtonProps={{
                    'aria-label': t(labelChangeStartTime),
                  }}
                  ampm={isMeridianFormat(values.timeStart)}
                  aria-label={t(labelStartTime)}
                  error={errors?.timeStart !== undefined}
                  helperText={errors?.timeStart}
                  value={values.timeStart}
                  onChange={changeDate('timeStart')}
                  {...timePickerProps}
                />
              </Grid>
            </Grid>
          </Grid>
          <Grid item>
            <FormHelperText>{t(labelTo)}</FormHelperText>
            <Grid container direction="row" spacing={1}>
              <Grid item style={{ width: 240 }}>
                <KeyboardDatePicker
                  KeyboardButtonProps={{
                    'aria-label': t(labelChangeEndDate),
                  }}
                  aria-label={t(labelEndDate)}
                  error={errors?.dateEnd !== undefined}
                  helperText={errors?.dateEnd}
                  value={values.dateEnd}
                  onChange={changeDate('dateEnd')}
                  {...datePickerProps}
                />
              </Grid>
              <Grid item style={{ width: 200 }}>
                <KeyboardTimePicker
                  KeyboardButtonProps={{
                    'aria-label': t(labelChangeEndTime),
                  }}
                  ampm={isMeridianFormat(values.timeEnd)}
                  aria-label={t(labelEndTime)}
                  disableToolbar={not(isMeridianFormat(values.timeEnd))}
                  error={errors?.timeEnd !== undefined}
                  helperText={errors?.timeEnd}
                  value={values.timeEnd}
                  onChange={changeDate('timeEnd')}
                  {...timePickerProps}
                />
              </Grid>
            </Grid>
          </Grid>
          <Grid item>
            <FormControlLabel
              control={
                <Checkbox
                  checked={values.fixed}
                  color="primary"
                  inputProps={{ 'aria-label': t(labelFixed) }}
                  size="small"
                  onChange={handleChange('fixed')}
                />
              }
              label={t(labelFixed)}
            />
          </Grid>
          <Grid item>
            <FormHelperText>{t(labelDuration)}</FormHelperText>
            <Grid container direction="row" spacing={1}>
              <Grid item style={{ width: 150 }}>
                <TextField
                  disabled={values.fixed}
                  error={errors?.duration?.value}
                  type="number"
                  value={values.duration.value}
                  onChange={handleChange('duration.value')}
                />
              </Grid>
              <Grid item style={{ width: 150 }}>
                <SelectField
                  disabled={values.fixed}
                  options={[
                    {
                      id: 'seconds',
                      name: t(labelSeconds),
                    },
                    {
                      id: 'minutes',
                      name: t(labelMinutes),
                    },
                    {
                      id: 'hours',
                      name: t(labelHours),
                    },
                  ]}
                  selectedOptionId={values.duration.unit}
                  onChange={handleChange('duration.unit')}
                />
              </Grid>
            </Grid>
          </Grid>
          <Grid item>
            <TextField
              fullWidth
              multiline
              error={errors?.comment}
              label={t(labelComment)}
              rows={3}
              value={values.comment}
              onChange={handleChange('comment')}
            />
          </Grid>
          {hasHosts && (
            <Grid item>
              <FormControlLabel
                control={
                  <Checkbox
                    checked={
                      canDowntimeServices() && values.isDowntimeWithServices
                    }
                    color="primary"
                    disabled={!canDowntimeServices()}
                    inputProps={{ 'aria-label': labelSetDowntimeOnServices }}
                    size="small"
                    onChange={handleChange('isDowntimeWithServices')}
                  />
                }
                label={t(labelSetDowntimeOnServices)}
              />
            </Grid>
          )}
        </Grid>
      </MuiPickersUtilsProvider>
>>>>>>> centreon/dev-21.10.x
    </Dialog>
  );
};

export default DialogDowntime;
