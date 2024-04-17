import { ChangeEvent, useState } from 'react';

import { propEq, reject } from 'ramda';
import { useFormikContext } from 'formik';

import { SelectEntry } from '@centreon/ui';

import { NamedEntity, ResourceAccessRule } from '../../../models';
import { findContactsEndpoint } from '../../api/endpoints';

interface UseContactsSelectorState {
  checked: boolean;
  contacts: Array<NamedEntity>;
  deleteContactItem: ({ option, contacts }) => void;
  getEndpoint: () => string;
  onCheckboxChange: (event: ChangeEvent<HTMLInputElement>) => void;
  onMultiselectChange: () => (_, contacts: Array<SelectEntry>) => void;
}

const useContactsSelector = (): UseContactsSelectorState => {
  const { values, setFieldValue, setFieldTouched } =
    useFormikContext<ResourceAccessRule>();

  const [allContacts, setAllContacts] = useState<boolean>(false);

  const deleteContactItem = ({ option, contacts }): void => {
    const newContacts = reject(propEq(option.id, 'id'), contacts);
    setFieldValue('contacts', newContacts);
  };

  const getEndpoint = (): string => findContactsEndpoint;

  const onCheckboxChange = (event: ChangeEvent<HTMLInputElement>): void => {
    setAllContacts(event.target.checked);
    setFieldValue('allContacts', allContacts);
    setFieldValue('contacts', []);
    setFieldTouched('contacts', true, false);
  };

  const onMultiselectChange = () => (_, contacts: Array<SelectEntry>) => {
    setFieldValue('contacts', contacts);
  };

  return {
    checked: allContacts,
    contacts: values.contacts,
    deleteContactItem,
    getEndpoint,
    onCheckboxChange,
    onMultiselectChange
  };
};

export default useContactsSelector;
