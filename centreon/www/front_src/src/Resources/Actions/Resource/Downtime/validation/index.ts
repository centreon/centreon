import dayjs from 'dayjs';
<<<<<<< HEAD
import { isNil } from 'ramda';
=======
import { FormikErrors } from 'formik';
>>>>>>> centreon/dev-21.10.x
import * as Yup from 'yup';

import {
  labelEndDateGreaterThanStartDate,
<<<<<<< HEAD
  labelInvalidFormat,
  labelMaxDuration1Year,
  labelRequired,
} from '../../../../translatedLabels';

const getValidationSchema = (t: (string) => string): unknown => {
  const dateSchema = Yup.date()
    .typeError(t(labelInvalidFormat))
    .required(t(labelRequired))
    .nullable();

  return Yup.object().shape({
    comment: Yup.string().required(t(labelRequired)),
=======
  labelMaxDuration1Year,
  labelRequired,
} from '../../../../translatedLabels';
import { DateParams } from '../models';
import { formatDateInterval } from '../utils';

interface Props {
  t: (string) => string;
  values: DateParams;
}

const getDateEndError = ({ values, t }: Props): string | undefined => {
  const [start, end] = formatDateInterval(values);

  if (start >= end) {
    return t(labelEndDateGreaterThanStartDate);
  }

  const dateEndStartDifference = dayjs(start).diff(dayjs(end), 'year');

  if (dateEndStartDifference) {
    return t(labelMaxDuration1Year);
  }

  return undefined;
};

const validate = ({ values, t }: Props): FormikErrors<DateParams> => {
  if (
    values.dateStart &&
    values.timeStart &&
    values.dateEnd &&
    values.timeEnd
  ) {
    const dateEndError = getDateEndError({ t, values });

    return dateEndError
      ? {
          dateEnd: dateEndError,
        }
      : {};
  }

  return {};
};

const getValidationSchema = (t: (string) => string): unknown =>
  Yup.object().shape({
    comment: Yup.string().required(t(labelRequired)),
    dateEnd: Yup.string().required(t(labelRequired)).nullable(),
    dateStart: Yup.string().required(t(labelRequired)).nullable(),
>>>>>>> centreon/dev-21.10.x
    duration: Yup.object().when('fixed', (fixed, schema) => {
      return !fixed
        ? schema.shape({
            unit: Yup.string().required(t(labelRequired)),
            value: Yup.string().required(t(labelRequired)),
          })
        : schema;
    }),
<<<<<<< HEAD
    endTime: dateSchema.when(
      'startTime',
      (startTime: Date | null): Yup.AnySchema => {
        if (isNil(startTime) || !dayjs(startTime).isValid()) {
          return dateSchema;
        }

        return dateSchema
          .min(
            dayjs(startTime).add(dayjs.duration({ minutes: 1 })),
            t(labelEndDateGreaterThanStartDate),
          )
          .max(
            dayjs(startTime).add(dayjs.duration({ years: 1 })),
            t(labelMaxDuration1Year),
          );
      },
    ),
    fixed: Yup.boolean(),
    startTime: dateSchema,
  });
};

export { getValidationSchema };
=======
    fixed: Yup.boolean(),
    timeEnd: Yup.string().required(t(labelRequired)).nullable(),
    timeStart: Yup.string().required(t(labelRequired)).nullable(),
  });

export { validate, getValidationSchema };
>>>>>>> centreon/dev-21.10.x
