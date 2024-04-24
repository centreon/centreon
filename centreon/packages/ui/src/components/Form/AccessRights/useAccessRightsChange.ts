import { useEffect } from 'react';

import { useAtomValue } from 'jotai';

import { useDeepCompare } from '../../../utils';

import { valuesAtom } from './atoms';
import { formatValueForSubmition } from './utils';
import { AccessRightInitialValues } from './models';

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
