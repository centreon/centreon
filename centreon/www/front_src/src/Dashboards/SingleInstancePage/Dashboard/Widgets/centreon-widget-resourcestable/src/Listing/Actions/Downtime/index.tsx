import { useEffect } from 'react';

import dayjs from 'dayjs';
import localizedFormat from 'dayjs/plugin/localizedFormat';
import timezonePlugin from 'dayjs/plugin/timezone';
import utcPlugin from 'dayjs/plugin/utc';
import { useFormik } from 'formik';
import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';

import { useLocaleDateTimeFormat, useRequest, useSnackbar } from '@centreon/ui';
import { downtimeAtom, userAtom } from '@centreon/ui-context';

import { Resource } from '../../models';
import {
  labelDowntimeBy,
  labelDowntimeCommandSent
} from '../../translatedLabels';
import { setDowntimeOnResources } from '../api';

import DialogDowntime from './Dialog';
import { getValidationSchema } from './validation';

dayjs.extend(localizedFormat);
dayjs.extend(utcPlugin);
dayjs.extend(timezonePlugin);

interface Props {
  onClose: () => void;
  onSuccess: () => void;
  resources: Array<Resource>;
}

export interface DowntimeFormValues {
  comment?: string;
  duration: {
    unit: string;
    value: number;
  };
  endTime: Date;
  fixed: boolean;
  isDowntimeWithServices: boolean;
  startTime: Date;
}

export interface DowntimeToPost {
  comment?: string;
  duration: {
    unit: string;
    value: number;
  };
  endTime: string;
  fixed: boolean;
  isDowntimeWithServices: boolean;
  startTime: string;
}

const DowntimeForm = ({
  resources,
  onClose,
  onSuccess
}: Props): JSX.Element | null => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();

  const { toIsoString } = useLocaleDateTimeFormat();

  const {
    sendRequest: sendSetDowntimeOnResources,
    sending: sendingSetDowntingOnResources
  } = useRequest({
    request: setDowntimeOnResources
  });

  const { alias } = useAtomValue(userAtom);
  const downtime = useAtomValue(downtimeAtom);

  const currentDate = new Date();
  const defaultEndDate = dayjs(currentDate)
    .add(downtime.duration, 'seconds')
    .toDate();

  const form = useFormik<DowntimeFormValues>({
    initialValues: {
      comment: undefined,
      duration: {
        unit: 'seconds',
        value: downtime.duration
      },
      endTime: defaultEndDate,
      fixed: downtime.fixed,
      isDowntimeWithServices: downtime.with_services,
      startTime: currentDate
    },
    onSubmit: (values, { setSubmitting }) => {
      setSubmitting(true);

      const { startTime, endTime } = values;

      const unitMultipliers = {
        hours: 3600,
        minutes: 60,
        seconds: 1
      };
      const durationDivider = unitMultipliers?.[values.duration.unit] || 1;
      const duration = values.duration.value * durationDivider;

      sendSetDowntimeOnResources({
        params: {
          ...values,
          duration,
          endTime: toIsoString(endTime),
          startTime: toIsoString(startTime)
        },
        resources
      }).then(() => {
        showSuccessMessage(t(labelDowntimeCommandSent));
        onSuccess();
      });
    },
    validationSchema: getValidationSchema(t)
  });

  useEffect(() => {
    form.setFieldValue('comment', `${t(labelDowntimeBy)} ${alias}`);
  }, []);

  return (
    <DialogDowntime
      canConfirm={form.isValid}
      errors={form.errors}
      handleChange={form.handleChange}
      resources={resources}
      setFieldValue={form.setFieldValue}
      submitting={sendingSetDowntingOnResources}
      values={form.values}
      onCancel={onClose}
      onConfirm={form.submitForm}
    />
  );
};

export default DowntimeForm;
