import { ChangeEvent } from 'react';

import { useFormikContext } from 'formik';
import { useAtom } from 'jotai';
import { propEq, reject } from 'ramda';

import { NamedEntity, ResourceAccessRule } from '../../../models';
import { allContactGroupsSelectedAtom } from '../../../atom';

interface UseContactGroupssSelectorState {
  checked: boolean;
  contactGroups: Array<NamedEntity>;
  deleteContactGroupsItem: ({ contactGroups, option }) => void;
  onCheckboxChange: (event: ChangeEvent<HTMLInputElement>) => void;
  onMultiSelectChange: () => (_, contactGroups: Array<NamedEntity>) => void;
}

const useContactGroupsSelector = (): UseContactGroupssSelectorState => {
  const { values, setFieldValue } = useFormikContext<ResourceAccessRule>();

  const [allContactGroupsSelected, setAllContactGroupsSelected] = useAtom(
    allContactGroupsSelectedAtom
  );

  const deleteContactGroupsItem = ({ contactGroups, option }): void => {
    const newContactGroups = reject(propEq(option.id, 'id'), contactGroups);
    setFieldValue('contacts', newContactGroups);
  };

  const onCheckboxChange = (event: ChangeEvent<HTMLInputElement>): void => {
    setFieldValue('contactGroups', []);
    setFieldValue('allContactGroups', event.target.checked);
    setAllContactGroupsSelected(event.target.checked);
  };

  const onMultiSelectChange = () => (_, contactGroups: Array<NamedEntity>) => {
    setFieldValue('contactGroups', contactGroups);
  };

  return {
    checked: allContactGroupsSelected,
    contactGroups: values.contactGroups,
    deleteContactGroupsItem,
    onCheckboxChange,
    onMultiSelectChange
  };
};

export default useContactGroupsSelector;
