import dayjs from 'dayjs';
import { isNil } from 'ramda';
import * as Yup from 'yup';

import {
  labelEndDateGreaterThanStartDate,
  labelInvalidFormat,
  labelMaxDuration1Year,
  labelRequired
} from '../../../../translatedLabels';

const getValidationSchema = (t: (string) => string): unknown => {
  const dateSchema = Yup.date()
    .typeError(t(labelInvalidFormat))
    .required(t(labelRequired))
    .nullable();

  return Yup.object().shape({
    comment: Yup.string().required(t(labelRequired)),
    duration: Yup.object().when('fixed', (fixed, schema) => {
      return !fixed
        ? schema.shape({
            unit: Yup.string().required(t(labelRequired)),
            value: Yup.string().required(t(labelRequired))
          })
        : schema;
    }),
    endTime: dateSchema.when(
      'startTime',
      (startTime: Date | null): Yup.AnySchema => {
        if (isNil(startTime) || !dayjs(startTime).isValid()) {
          return dateSchema;
        }

        return dateSchema
          .min(
            dayjs(startTime).add(1, 'minute'),
            t(labelEndDateGreaterThanStartDate)
          )
          .max(dayjs(startTime).add(1, 'year'), t(labelMaxDuration1Year));
      }
    ),
    fixed: Yup.boolean(),
    startTime: dateSchema
  });
};

export { getValidationSchema };
