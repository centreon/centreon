import dayjs from 'dayjs';
import type { FormikErrors, FormikHandlers, FormikValues } from 'formik';
import { useAtomValue } from 'jotai';
import { isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  Alert,
  Checkbox,
  FormControlLabel,
  FormHelperText,
  Stack
} from '@mui/material';
import { Box } from '@mui/system';
import {
  DesktopDateTimePicker,
  LocalizationProvider
} from '@mui/x-date-pickers';
import { AdapterDayjs } from '@mui/x-date-pickers/AdapterDayjs';

import { Dialog, SelectField, TextField } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import type { Resource } from '../../../models';
import {
  labelCancel,
  labelComment,
  labelDowntime,
  labelDuration,
  labelEndTime,
  labelFixed,
  labelHours,
  labelMinutes,
  labelSeconds,
  labelSetDowntime,
  labelSetDowntimeOnServices,
  labelStartTime,
  labelTo,
  labelUnit
} from '../../../translatedLabels';
import useAclQuery from '../aclQuery';

import type { DowntimeFormValues } from '.';

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

const DialogDowntime = ({
  resources,
  canConfirm,
  onCancel,
  onConfirm,
  errors,
  values,
  submitting,
  handleChange,
  setFieldValue
}: Props): JSX.Element => {
  const { t } = useTranslation();

  const { getDowntimeDeniedTypeAlert, canDowntimeServices } = useAclQuery();

  const { locale, timezone } = useAtomValue(userAtom);

  const open = resources.length > 0;

  const hasHosts = resources.find((resource) => resource.type === 'host');

  const changeDate =
    (field) =>
    (value): void => {
      setFieldValue(field, value);
    };

  const deniedTypeAlert = getDowntimeDeniedTypeAlert(resources);

  const changeTime =
    (field) =>
    (newValue: dayjs.Dayjs | null): void => {
      const value = dayjs(newValue).toDate();

      changeDate(field)(value);
    };

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
      <LocalizationProvider
        adapterLocale={locale.substring(0, 2)}
        dateAdapter={AdapterDayjs}
      >
        {deniedTypeAlert && <Alert severity="warning">{deniedTypeAlert}</Alert>}
        <Stack spacing={2}>
          <Box
            alignItems="center"
            display="grid"
            gap={1}
            gridTemplateColumns="1fr auto 1fr"
          >
            <DesktopDateTimePicker<dayjs.Dayjs>
              maxDate={dayjs(maxEndDate)}
              slotProps={{
                textField: {
                  'aria-label': t(labelStartTime) as string
                }
              }}
              timezone={timezone}
              value={dayjs(values.startTime)}
              onChange={changeTime('startTime')}
            />
            <FormHelperText>{t(labelTo)}</FormHelperText>
            <DesktopDateTimePicker<dayjs.Dayjs>
              slotProps={{
                textField: {
                  'aria-label': t(labelEndTime) as string
                }
              }}
              timezone={timezone}
              value={dayjs(values.endTime)}
              onChange={changeTime('endTime')}
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

            <Stack alignItems="center" direction="row" spacing={0.5}>
              <TextField
                autoSize
                ariaLabel={t(labelDuration) as string}
                autoSizeDefaultWidth={168}
                dataTestId={labelDuration}
                disabled={values.fixed}
                error={errors?.duration?.value}
                type="number"
                value={values.duration.value}
                onChange={handleChange('duration.value')}
              />

              <Stack
                direction="row"
                justifyContent="flex-end"
                spacing={1}
                width="100%"
              >
                <SelectField
                  dataTestId={labelUnit}
                  disabled={values.fixed}
                  options={[
                    {
                      id: 'seconds',
                      name: t(labelSeconds)
                    },
                    {
                      id: 'minutes',
                      name: t(labelMinutes)
                    },
                    {
                      id: 'hours',
                      name: t(labelHours)
                    }
                  ]}
                  selectedOptionId={values.duration.unit}
                  onChange={handleChange('duration.unit')}
                />
                <FormControlLabel
                  control={
                    <Checkbox
                      checked={values.fixed}
                      color="primary"
                      inputProps={{ 'aria-label': t(labelFixed) as string }}
                      size="small"
                      onChange={handleChange('fixed')}
                    />
                  }
                  label={t(labelFixed) as string}
                />
              </Stack>
            </Stack>
          </Stack>
          <TextField
            fullWidth
            multiline
            dataTestId={labelComment}
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
    </Dialog>
  );
};

export default DialogDowntime;
