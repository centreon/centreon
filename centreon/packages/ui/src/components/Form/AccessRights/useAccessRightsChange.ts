import { useEffect } from 'react';

import { useAtomValue } from 'jotai';

import { useDeepCompare } from '../../../utils';

import { valuesAtom } from './atoms';
import { AccessRightInitialValues } from './models';
import { formatValueForSubmition } from './utils';

export const useAccessRightsChange = (
  onChange?: (values: Array<AccessRightInitialValues>) => void
): void => {
  const values = useAtomValue(valuesAtom);

  useEffect(
    () => {
      if (!onChange) {
        return;
      }

      onChange(
        values
          .filter(({ isRemoved }) => !isRemoved)
          .map(formatValueForSubmition)
      );
    },
    useDeepCompare([values])
  );
};
