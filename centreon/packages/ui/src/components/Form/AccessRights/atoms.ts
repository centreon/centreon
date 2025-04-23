import { atom } from 'jotai';
import { equals, pluck, prop, remove, update } from 'ramda';

import { AccessRight, AccessRightInitialValues, ContactType } from './models';

export const valuesAtom = atom<Array<AccessRight>>([]);
export const initialValuesAtom = atom<Array<AccessRightInitialValues>>([]);
export const contactTypeAtom = atom<ContactType>(ContactType.Contact);

export const accessRightIdsDerivedAtom = atom((get) => {
  const values = get(valuesAtom);

  return pluck('id', values);
});

export const addAccessRightDerivedAtom = atom(
  null,
  (get, set, share: AccessRightInitialValues) => {
    const values = get(valuesAtom);

    set(valuesAtom, [
      {
        ...share,
        isAdded: true,
        isRemoved: false,
        isUpdated: false
      },
      ...values
    ]);
  }
);

export const updateContactRoleDerivedAtom = atom(
  null,
  (get, set, { id, index, newRole }) => {
    const values = get(valuesAtom);
    const initialValues = get(initialValuesAtom);

    const initialAccesRight = initialValues.find((value) =>
      equals(id, value.id)
    ) as AccessRightInitialValues;
    const accessRight = values.find((value) =>
      equals(id, value.id)
    ) as AccessRight;

    set(
      valuesAtom,
      update(
        index,
        {
          ...accessRight,
          isUpdated: !equals(initialAccesRight?.role, newRole),
          role: newRole
        },
        values
      )
    );
  }
);

export const removeAccessRightDerivedAtom = atom(
  null,
  (get, set, { index, recover = false }) => {
    const values = get(valuesAtom);

    const accessRight = values[index];

    if (accessRight.isAdded) {
      set(valuesAtom, remove(index, 1, values));

      return;
    }

    set(
      valuesAtom,
      update(index, {
        ...accessRight,
        isRemoved: !recover
      })
    );
  }
);

export const statsDerivedAtom = atom((get) => {
  const values = get(valuesAtom);

  const addedItems = values.filter(prop('isAdded')).length;
  const updatedItems = values.filter(prop('isUpdated')).length;
  const removedItems = values.filter(prop('isRemoved')).length;

  return {
    addedItems,
    hasStats: Boolean(addedItems || updatedItems || removedItems),
    removedItems,
    updatedItems
  };
});
