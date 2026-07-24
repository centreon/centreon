import { platformFeaturesAtom } from '@centreon/ui-context';

import { useAtomValue } from 'jotai';
import { useMemo } from 'react';
import { mixed, object, string } from 'yup';

import { PollerEnvironment } from '../models';

export const useValidationSchema = () => {
  const platformFeatures = useAtomValue(platformFeaturesAtom);

  return useMemo(
    () =>
      object({
        centralAddress: platformFeatures?.isCloudPlatform
          ? string()
          : string().trim().required(),
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
