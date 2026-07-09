import { buildListingEndpoint } from '@centreon/ui';

import { useFormikContext } from 'formik';
import { useSetAtom } from 'jotai';
import { propEq, reject } from 'ramda';
import { ChangeEvent } from 'react';

import { allContactsSelectedAtom } from '../../../atom';
import { NamedEntity, ResourceAccessRule } from '../../../models';
import { findContactsEndpoint } from '../../api/endpoints';

interface UseContactsSelectorState {
  checked: boolean;
  contacts: Array<NamedEntity>;
  deleteContactsItem: ({
    contacts,
    option
  }: {
    contacts: Array<NamedEntity>;
    option: NamedEntity;
  }) => void;
  getEndpoint: () => (parameters: Record<string, unknown>) => string;
  onCheckboxChange: (event: ChangeEvent<HTMLInputElement>) => void;
  onMultiSelectChange: () => (_: unknown, contacts: Array<NamedEntity>) => void;
}

const useContactsSelector = (): UseContactsSelectorState => {
  const { values, setFieldValue, setFieldTouched } =
    useFormikContext<ResourceAccessRule>();

  const setAllContactsSelected = useSetAtom(allContactsSelectedAtom);

  const deleteContactsItem = ({
    contacts,
    option
  }: {
    contacts: Array<NamedEntity>;
    option: NamedEntity;
  }): void => {
    const newContacts = reject(propEq(option.id, 'id'), contacts);
    setFieldValue('contacts', newContacts);
  };

  const getEndpoint =
    () =>
    (parameters: Record<string, unknown>): string => {
      return buildListingEndpoint({
        baseEndpoint: findContactsEndpoint,
        customQueryParameters: undefined,
        parameters
      });
    };

  const onCheckboxChange = (event: ChangeEvent<HTMLInputElement>): void => {
    setFieldValue('contacts', []);
    setFieldValue('allContacts', event.target.checked);
    setFieldTouched('contacts', true, false);
    setFieldTouched('allContacts', true, false);
    setAllContactsSelected(event.target.checked);
  };

  const onMultiSelectChange =
    () =>
    (_: unknown, contacts: Array<NamedEntity>): void => {
      setFieldValue('contacts', contacts);
    };

  return {
    checked: values.allContacts,
    contacts: values.contacts,
    deleteContactsItem,
    getEndpoint,
    onCheckboxChange,
    onMultiSelectChange
  };
};

export default useContactsSelector;
