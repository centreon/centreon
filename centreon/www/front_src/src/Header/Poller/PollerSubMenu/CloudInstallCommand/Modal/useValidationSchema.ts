import { useMemo } from 'react';
import { array, mixed, object, string } from 'yup';

import { PollerEnvironment } from '../models';

export const useValidationSchema = () => {
  return useMemo(
    () =>
      object({
        environment: mixed<PollerEnvironment>()
          .oneOf(Object.values(PollerEnvironment))
          .required(),
        pollerName: string().trim().required(),
        token: array()
          .of(
            object({
              id: string().required(),
              name: string().required()
            })
          )
          .min(1)
          .required()
      }),
    []
  );
};
