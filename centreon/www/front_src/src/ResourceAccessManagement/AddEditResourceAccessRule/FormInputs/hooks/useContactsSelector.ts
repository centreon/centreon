import { ChangeEvent } from 'react';

import { useFormikContext } from 'formik';
import { useAtom } from 'jotai';
import { propEq, reject } from 'ramda';

import { NamedEntity, ResourceAccessRule } from '../../../models';
import { allContactsSelectedAtom } from '../../../atom';

interface UseContactsSelectorState {
  checked: boolean;
  contacts: Array<NamedEntity>;
  deleteContactsItem: ({ contacts, option }) => void;
  onCheckboxChange: (event: ChangeEvent<HTMLInputElement>) => void;
  onMultiSelectChange: () => (_, contacts: Array<NamedEntity>) => void;
}

const useContactsSelector = (): UseContactsSelectorState => {
  const { values, setFieldValue } = useFormikContext<ResourceAccessRule>();

  const [allContactsSelected, setAllContactsSelected] = useAtom(
    allContactsSelectedAtom
  );

  const deleteContactsItem = ({ contacts, option }): void => {
    const newContacts = reject(propEq(option.id, 'id'), contacts);
    setFieldValue('contacts', newContacts);
  };

  const onCheckboxChange = (event: ChangeEvent<HTMLInputElement>): void => {
    setFieldValue('contacts', []);
    setFieldValue('allContacts', event.target.checked);
    setAllContactsSelected(event.target.checked);
  };

  const onMultiSelectChange = () => (_, contacts: Array<NamedEntity>) => {
    setFieldValue('contacts', contacts);
  };

  return {
    checked: allContactsSelected,
    contacts: values.contacts,
    deleteContactsItem,
    onCheckboxChange,
    onMultiSelectChange
  };
};

export default useContactsSelector;
