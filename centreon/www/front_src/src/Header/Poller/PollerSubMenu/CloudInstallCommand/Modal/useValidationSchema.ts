import { useMemo } from 'react';
import { mixed, object, string } from 'yup';

import { PollerEnvironment } from '../models';

export const useValidationSchema = () => {
  return useMemo(
    () =>
      object({
        environment: mixed<PollerEnvironment>()
          .oneOf(Object.values(PollerEnvironment))
          .required(),
        pollerAddress: string().trim().required(),
        pollerName: string().trim().required(),
        token: object({
          id: string().required(),
          name: string().required()
        })
          .nullable()
          .required()
      }),
    []
  );
};
