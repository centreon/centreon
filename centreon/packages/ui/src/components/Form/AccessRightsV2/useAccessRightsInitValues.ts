import { useEffect } from 'react';

import { useSetAtom } from 'jotai';

import { initialValuesAtom, valuesAtom } from './atoms';
import { AccessRightInitialValues, ContactType } from './models';

interface Props {
  initialValues: Array<AccessRightInitialValues>;
}

export const useAccessRightsInitValues = ({ initialValues }: Props): void => {
  const setValues = useSetAtom(valuesAtom);
  const setInitialValues = useSetAtom(initialValuesAtom);

  useEffect(() => {
    setInitialValues(
      initialValues.map((value) => ({
        ...value,
        id: `${
          value.isContactGroup ? ContactType.ContactGroup : ContactType.Contact
        }_${value.id}`
      }))
    );
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
  }, [initialValues]);
};
