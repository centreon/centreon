<<<<<<< HEAD
import { useEffect } from 'react';

import { useFormik } from 'formik';
import { useTranslation } from 'react-i18next';
import { useAtomValue } from 'jotai/utils';
import dayjs from 'dayjs';

import { useSnackbar, useRequest, useLocaleDateTimeFormat } from '@centreon/ui';
import { downtimeAtom, userAtom } from '@centreon/ui-context';
=======
import * as React from 'react';

import { useFormik } from 'formik';
import { useTranslation } from 'react-i18next';

import { useSnackbar, useRequest, useLocaleDateTimeFormat } from '@centreon/ui';
import { useUserContext } from '@centreon/ui-context';
>>>>>>> centreon/dev-21.10.x

import {
  labelDowntimeCommandSent,
  labelDowntimeBy,
} from '../../../translatedLabels';
import { Resource } from '../../../models';
import { setDowntimeOnResources } from '../../api';

import DialogDowntime from './Dialog';
<<<<<<< HEAD
import { getValidationSchema } from './validation';
=======
import { getValidationSchema, validate } from './validation';
import { formatDateInterval } from './utils';
>>>>>>> centreon/dev-21.10.x

interface Props {
  onClose: () => void;
  onSuccess: () => void;
  resources: Array<Resource>;
}

export interface DowntimeFormValues {
  comment?: string;
<<<<<<< HEAD
=======
  dateEnd: Date;
  dateStart: Date;
>>>>>>> centreon/dev-21.10.x
  duration: {
    unit: string;
    value: number;
  };
<<<<<<< HEAD
  endTime: Date;
  fixed: boolean;
  isDowntimeWithServices: boolean;
  startTime: Date;
=======
  fixed: boolean;
  isDowntimeWithServices: boolean;
  timeEnd: Date;
  timeStart: Date;
>>>>>>> centreon/dev-21.10.x
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
  onSuccess,
}: Props): JSX.Element | null => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();

  const { toIsoString } = useLocaleDateTimeFormat();

  const {
    sendRequest: sendSetDowntimeOnResources,
    sending: sendingSetDowntingOnResources,
  } = useRequest({
    request: setDowntimeOnResources,
  });
<<<<<<< HEAD

  const { alias } = useAtomValue(userAtom);
  const downtime = useAtomValue(downtimeAtom);

  const currentDate = new Date();
  const defaultEndDate = dayjs(currentDate)
    .add(dayjs.duration({ seconds: downtime.duration }))
    .toDate();
=======
  const { alias, downtime } = useUserContext();

  const currentDate = new Date();

  const defaultDurationInMs = downtime.duration * 1000;
  const defaultEndDate = new Date(currentDate.getTime() + defaultDurationInMs);
>>>>>>> centreon/dev-21.10.x

  const form = useFormik<DowntimeFormValues>({
    initialValues: {
      comment: undefined,
<<<<<<< HEAD
=======
      dateEnd: defaultEndDate,
      dateStart: currentDate,
>>>>>>> centreon/dev-21.10.x
      duration: {
        unit: 'seconds',
        value: downtime.duration,
      },
<<<<<<< HEAD
      endTime: defaultEndDate,
      fixed: downtime.fixed,
      isDowntimeWithServices: downtime.with_services,
      startTime: currentDate,
=======
      fixed: downtime.fixed,
      isDowntimeWithServices: downtime.with_services,
      timeEnd: defaultEndDate,
      timeStart: currentDate,
>>>>>>> centreon/dev-21.10.x
    },
    onSubmit: (values, { setSubmitting }) => {
      setSubmitting(true);

<<<<<<< HEAD
      const { startTime, endTime } = values;
=======
      const [startTime, endTime] = formatDateInterval(values);
>>>>>>> centreon/dev-21.10.x

      const unitMultipliers = {
        hours: 3600,
        minutes: 60,
        seconds: 1,
      };
      const durationDivider = unitMultipliers?.[values.duration.unit] || 1;
      const duration = values.duration.value * durationDivider;

      sendSetDowntimeOnResources({
        params: {
          ...values,
          duration,
          endTime: toIsoString(endTime),
          startTime: toIsoString(startTime),
        },
        resources,
      }).then(() => {
        showSuccessMessage(t(labelDowntimeCommandSent));
        onSuccess();
      });
    },
<<<<<<< HEAD
    validationSchema: getValidationSchema(t),
  });

  useEffect(() => {
=======
    validate: (values) => validate({ t, values }),
    validationSchema: getValidationSchema(t),
  });

  React.useEffect(() => {
>>>>>>> centreon/dev-21.10.x
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
