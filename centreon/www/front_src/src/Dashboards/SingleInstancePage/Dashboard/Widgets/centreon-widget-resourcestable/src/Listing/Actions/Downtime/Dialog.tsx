import { FormikErrors, FormikHandlers, FormikValues } from 'formik';
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
  DateTimePickerInput,
  Dialog,
  SelectField,
  TextField
} from '@centreon/ui';

import { Resource } from '../../models';
import {
  labelCancel,
  labelComment,
  labelDowntime,
  labelDuration,
  labelFixed,
  labelHours,
  labelMinutes,
  labelSeconds,
  labelSetDowntime,
  labelSetDowntimeOnServices,
  labelTo,
  labelUnit
} from '../../translatedLabels';
import useAclQuery from '../aclQuery';

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

  const open = resources.length > 0;

  const hasHosts = resources.find((resource) => resource.type === 'host');

  const deniedTypeAlert = getDowntimeDeniedTypeAlert(resources);

  const changeTime = ({ date, property }): void => {
    setFieldValue(property, date);
  };

  return (
    <Dialog
      confirmDisabled={!canConfirm}
      data-testid="dialogDowntime"
      labelCancel={t(labelCancel)}
      labelConfirm={t(labelSetDowntime)}
      labelTitle={t(labelDowntime)}
      open={open}
      submitting={submitting}
      onCancel={onCancel}
      onClose={onCancel}
      onConfirm={onConfirm}
    >
      <>
        {deniedTypeAlert && <Alert severity="warning">{deniedTypeAlert}</Alert>}
        <Stack spacing={2}>
          <Box
            alignItems="center"
            display="grid"
            gap={1}
            gridTemplateColumns="1fr auto 1fr"
          >
            <DateTimePickerInput
              changeDate={changeTime}
              date={values.startTime}
              maxDate={maxEndDate}
            />
            <FormHelperText>{t(labelTo)}</FormHelperText>
            <DateTimePickerInput
              changeDate={changeTime}
              date={values.endTime}
              property="endTime"
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
                ariaLabel={t(labelDuration) as string}
                dataTestId={labelDuration}
                disabled={values.fixed}
                error={errors?.duration?.value}
                type="number"
                value={values.duration.value}
                onChange={handleChange('duration.value')}
              />
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
      </>
    </Dialog>
  );
};

export default DialogDowntime;
