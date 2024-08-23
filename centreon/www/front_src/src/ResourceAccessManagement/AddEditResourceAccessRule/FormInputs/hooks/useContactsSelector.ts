import { ChangeEvent } from 'react';

import { useFormikContext } from 'formik';
import { useSetAtom } from 'jotai';
import { propEq, reject } from 'ramda';

import { buildListingEndpoint } from '@centreon/ui';

import { allContactsSelectedAtom } from '../../../atom';
import { NamedEntity, ResourceAccessRule } from '../../../models';
import { findContactsEndpoint } from '../../api/endpoints';

interface UseContactsSelectorState {
  checked: boolean;
  contacts: Array<NamedEntity>;
  deleteContactsItem: ({ contacts, option }) => void;
  getEndpoint: () => (parameters) => string;
  onCheckboxChange: (event: ChangeEvent<HTMLInputElement>) => void;
  onMultiSelectChange: () => (_, contacts: Array<NamedEntity>) => void;
}

const useContactsSelector = (): UseContactsSelectorState => {
  const { values, setFieldValue, setFieldTouched } =
    useFormikContext<ResourceAccessRule>();

  const setAllContactsSelected = useSetAtom(allContactsSelectedAtom);

  const deleteContactsItem = ({ contacts, option }): void => {
    const newContacts = reject(propEq(option.id, 'id'), contacts);
    setFieldValue('contacts', newContacts);
  };

  const getEndpoint =
    () =>
    (parameters): string => {
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

  const onMultiSelectChange = () => (_, contacts: Array<NamedEntity>) => {
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
