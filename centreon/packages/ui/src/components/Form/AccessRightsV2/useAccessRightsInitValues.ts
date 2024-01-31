import { useMemo } from 'react';

import { useAtom, useSetAtom } from 'jotai';
import { equals } from 'ramda';

import { initialValuesAtom, valuesAtom } from './atoms';
import { AccessRightInitialValues, ContactType } from './models';

interface Props {
  initialValues: Array<AccessRightInitialValues>;
}

export const useAccessRightsInitValues = ({
  initialValues
}: Props): (() => void) => {
  const [currentInitialValues, setInitialValues] = useAtom(initialValuesAtom);
  const setValues = useSetAtom(valuesAtom);

  const formattedInitialValues = useMemo(
    () =>
      initialValues.map((value) => ({
        ...value,
        id: `${
          value.isContactGroup ? ContactType.ContactGroup : ContactType.Contact
        }_${value.id}`
      })),
    [initialValues]
  );

  if (!equals(formattedInitialValues, currentInitialValues)) {
    setInitialValues(formattedInitialValues);
    setValues(
      initialValues.map((value) => ({
        isAdded: false,
        isRemoved: false,
        isUpdated: false,
        ...value,
        id: `${
          value.isContactGroup ? ContactType.ContactGroup : ContactType.Contact
        }_${value.id}`
      }))
    );
  }

  const clearValues = (): void => {
    setInitialValues([]);
    setValues([]);
  };

  return clearValues;
};
