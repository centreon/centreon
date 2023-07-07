import { ReactElement, ReactNode, useMemo, useRef } from 'react';

import {
  atom,
  createStore,
  Provider,
  useAtom,
  useAtomValue,
  useStore
} from 'jotai';
import { useHydrateAtoms } from 'jotai/utils';

import {
  ContactAccessRightResource,
  ContactAccessRightStateResource,
  ContactGroupResource,
  ContactResource,
  RoleResource
} from './AccessRights.resource';
import { sortOnAddedStateFirstAndContactName } from './useAccessRightsForm.utils';

/** state */

type formOptions = {
  contacts: Array<ContactResource | ContactGroupResource>;
  roles: Array<RoleResource>;
};

const formOptionsAtom = atom<formOptions>({
  contacts: [],
  roles: []
});

const contactAccessRightsAtom = atom<Array<ContactAccessRightStateResource>>(
  []
);

type StateStats = {
  added: number;
  removed: number;
  updated: number;
};

const contactAccessRightsStateAtom = atom<{
  isDirty: boolean;
  stats: StateStats;
}>((get) => ({
  isDirty: get(contactAccessRightsAtom).some(
    ({ state }) => state !== 'unchanged'
  ),
  stats: {
    added: get(contactAccessRightsAtom).filter(({ state }) => state === 'added')
      .length,
    removed: get(contactAccessRightsAtom).filter(
      ({ state }) => state === 'removed'
    ).length,
    updated: get(contactAccessRightsAtom).filter(
      ({ state }) => state === 'updated'
    ).length
  }
}));

type callbacks = {
  onSubmit?: (values: Array<ContactAccessRightStateResource>) => void;
};

const callbacksAtom = atom<callbacks>({});

/** provider */

export type AccessRightsFormProviderProps = {
  children: ReactNode;
  initialValues?: Array<ContactAccessRightResource>;
  onSubmit?: callbacks['onSubmit'];
  options?: formOptions;
};

const AccessRightsFormProvider = ({
  children,
  initialValues,
  options,
  onSubmit
}: AccessRightsFormProviderProps): ReactElement => {
  const store = useRef(createStore()).current;

  const initialState = useMemo(
    () =>
      initialValues
        ?.map(
          (contactAccessRight) =>
            ({
              contactAccessRight,
              state: 'unchanged',
              stateHistory: []
            } as ContactAccessRightStateResource)
        )
        .sort(sortOnAddedStateFirstAndContactName),
    [initialValues]
  );

  useHydrateAtoms(
    [
      [formOptionsAtom, options],
      [contactAccessRightsAtom, initialState ?? []],
      [callbacksAtom, { onSubmit }]
    ],
    { store }
  );

  return <Provider store={store}>{children}</Provider>;
};

/** hook */

type UseAccessRightsForm = {
  addContactAccessRight: (
    contactAccessRight: ContactAccessRightResource
  ) => void;
  contactAccessRights: Array<ContactAccessRightStateResource>;
  isDirty: boolean;
  options: formOptions;
  removeContactAccessRight: (
    contactAccessRight: ContactAccessRightResource,
    restore?: boolean
  ) => void;
  stats: StateStats;
  submit: () => void;
  updateContactAccessRight: (
    contactAccessRight: ContactAccessRightResource
  ) => void;
};

const useAccessRightsForm = (): UseAccessRightsForm => {
  const store = useStore();

  const [contactAccessRights, setContactAccessRights] = useAtom(
    contactAccessRightsAtom,
    { store }
  );
  const { isDirty, stats } = useAtomValue(contactAccessRightsStateAtom, {
    store
  });
  const options = useAtomValue(formOptionsAtom, { store });
  const callbacks = useAtomValue(callbacksAtom, { store });

  const addContactAccessRight = (
    contactAccessRight: ContactAccessRightResource
  ): void =>
    setContactAccessRights((prev) => {
      const isContactAlreadyAdded = prev.some(
        ({ contactAccessRight: c }) =>
          c.contact?.id === contactAccessRight.contact?.id
      );

      return isContactAlreadyAdded
        ? prev
        : [
            ...prev,
            {
              contactAccessRight,
              state: 'added',
              stateHistory: []
            } as ContactAccessRightStateResource
          ].sort(sortOnAddedStateFirstAndContactName);
    });

  const removeContactAccessRight = (
    contactAccessRight: ContactAccessRightResource,
    restore = false
  ): void =>
    setContactAccessRights((prev) => {
      const contactAccessRightIndex = prev.findIndex(
        ({ contactAccessRight: prevContactAccessRight }) =>
          prevContactAccessRight.contact?.id === contactAccessRight.contact?.id
      );

      if (contactAccessRightIndex === -1) {
        return prev;
      }

      const { stateHistory } = prev[contactAccessRightIndex];

      if (restore) {
        return [
          ...prev.slice(0, contactAccessRightIndex),
          {
            ...prev[contactAccessRightIndex],
            state: stateHistory[stateHistory.length - 1],
            stateHistory: [...stateHistory, prev[contactAccessRightIndex].state]
          } as ContactAccessRightStateResource,
          ...prev.slice(contactAccessRightIndex + 1)
        ];
      }

      if (
        prev[contactAccessRightIndex].state === 'added' ||
        stateHistory[0] === 'added'
      ) {
        return [
          ...prev.slice(0, contactAccessRightIndex),
          ...prev.slice(contactAccessRightIndex + 1)
        ];
      }

      return [
        ...prev.slice(0, contactAccessRightIndex),
        {
          ...prev[contactAccessRightIndex],
          state: 'removed',
          stateHistory: [...stateHistory, prev[contactAccessRightIndex].state]
        } as ContactAccessRightStateResource,
        ...prev.slice(contactAccessRightIndex + 1)
      ];
    });

  const updateContactAccessRight = (
    contactAccessRight: ContactAccessRightResource
  ): void =>
    setContactAccessRights((prev) => {
      const contactAccessRightIndex = prev.findIndex(
        ({ contactAccessRight: prevContactAccessRight }) =>
          prevContactAccessRight.contact?.id === contactAccessRight.contact?.id
      );

      if (contactAccessRightIndex === -1) {
        return prev;
      }

      const { stateHistory } = prev[contactAccessRightIndex];

      const isAdded =
        prev[contactAccessRightIndex].state === 'added' ||
        stateHistory[0] === 'added';
      const isUpdated =
        prev[contactAccessRightIndex].state === 'updated' ||
        stateHistory.findIndex((state) => state === 'updated') <
          stateHistory.findIndex((state) => state === 'unchanged');

      // eslint-disable-next-line no-nested-ternary
      const state = isAdded ? 'added' : isUpdated ? 'unchanged' : 'updated';

      return [
        ...prev.slice(0, contactAccessRightIndex),
        {
          contactAccessRight,
          state,
          stateHistory: [...stateHistory, prev[contactAccessRightIndex].state]
        } as ContactAccessRightStateResource,
        ...prev.slice(contactAccessRightIndex + 1)
      ];
    });

  const submit = (): void => callbacks.onSubmit?.(contactAccessRights);

  return {
    addContactAccessRight,
    contactAccessRights,
    isDirty,
    options,
    removeContactAccessRight,
    stats,
    submit,
    updateContactAccessRight
    // TODO isInContactAccessRights(contact) => boolean
  };
};

export { AccessRightsFormProvider, useAccessRightsForm };
