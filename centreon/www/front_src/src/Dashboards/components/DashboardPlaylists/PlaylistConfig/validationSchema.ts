import { ObjectSchema, array, number, object, string } from 'yup';

import { PlaylistConfig } from '../models';
import { labelRequired } from '../../../SingleInstancePage/Dashboard/translatedLabels';
import {
  labelRotationTimeShouldBeAtLeast,
  labelRotationTimeShouldBeAtMost
} from '../../../translatedLabels';

export const getValidationSchema = (t): ObjectSchema<object, PlaylistConfig> =>
  object<PlaylistConfig>().shape({
    dashboards: array().of(
      object().shape({
        id: number(),
        order: number()
      })
    ),
    description: string().nullable(),
    name: string().required(t(labelRequired)),
    rotationTime: number()
      .required(t(labelRequired))
      .min(10, t(labelRotationTimeShouldBeAtLeast))
      .max(60, t(labelRotationTimeShouldBeAtMost))
  });
