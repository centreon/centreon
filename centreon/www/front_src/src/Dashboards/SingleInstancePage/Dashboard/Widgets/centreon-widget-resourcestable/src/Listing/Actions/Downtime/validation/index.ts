import dayjs from 'dayjs';
import { isNil } from 'ramda';
import { AnySchema, boolean, date, object, string } from 'yup';

import {
  labelEndDateGreaterThanStartDate,
  labelInvalidFormat,
  labelMaxDuration1Year,
  labelRequired
} from '../../../translatedLabels';

const getValidationSchema = (t: (string) => string): unknown => {
  const dateSchema = date()
    .typeError(t(labelInvalidFormat))
    .required(t(labelRequired))
    .nullable();

  return object().shape({
    comment: string().required(t(labelRequired)),
    duration: object().when('fixed', ([fixed], schema) => {
      return !fixed
        ? schema.shape({
            unit: string().required(t(labelRequired)),
            value: string().required(t(labelRequired))
          })
        : schema;
    }),
    endTime: dateSchema.when('startTime', ([startTime]): AnySchema => {
      if (isNil(startTime) || !dayjs(startTime).isValid()) {
        return dateSchema;
      }

      return dateSchema
        .min(
          dayjs(startTime).add(1, 'minute'),
          t(labelEndDateGreaterThanStartDate)
        )
        .max(dayjs(startTime).add(1, 'year'), t(labelMaxDuration1Year));
    }),
    fixed: boolean(),
    startTime: dateSchema
  });
};

export { getValidationSchema };
