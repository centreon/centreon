import { ChangeEvent } from 'react';

import { equals, propEq, reject } from 'ramda';
import { useFormikContext } from 'formik';
import { useAtom } from 'jotai';

import { NamedEntity, ResourceAccessRule } from '../../../models';
import {
  allContactGroupsSelectedAtom,
  allContactsSelectedAtom
} from '../../../atom';
import {
  findContactGroupsEndpoint,
  findContactsEndpoint
} from '../../api/endpoints';
import {
  labelAllContactGroups,
  labelAllContactGroupsSelected,
  labelAllContacts,
  labelAllContactsSelected,
  labelContactGroups,
  labelContacts
} from '../../../translatedLabels';

interface UseMultiConnectedAutocompleteState {
  checked: boolean;
  deleteItem: ({ option, value }) => void;
  getEndpoint: () => string;
  label: string;
  labelAll: string;
  labelAllSelected: string;
  onCheckboxChange: (event: ChangeEvent<HTMLInputElement>) => void;
  onMultiselectChange: () => (_, value: Array<NamedEntity>) => void;
  value: Array<NamedEntity>;
}

const useMultiConnectedAutocomplete = (
  type: string
): UseMultiConnectedAutocompleteState => {
  const { values, setFieldValue, setFieldTouched } =
    useFormikContext<ResourceAccessRule>();

  const [allContactsSelected, setAllContactsSelected] = useAtom(
    allContactsSelectedAtom
  );
  const [allContactGroupsSelected, setAllContactGroupsSelected] = useAtom(
    allContactGroupsSelectedAtom
  );

  const valueName = equals(type, 'contacts') ? 'contacts' : 'contactGroups';
  const allValueName = equals(type, 'contacts')
    ? 'allContacts'
    : 'allContactGroups';
  const checked = equals(type, 'contacts')
    ? allContactsSelected
    : allContactGroupsSelected;

  const label = equals(type, 'contacts') ? labelContacts : labelContactGroups;
  const labelAll = equals(type, 'contacts')
    ? labelAllContacts
    : labelAllContactGroups;
  const labelAllSelected = equals(type, 'contacts')
    ? labelAllContactsSelected
    : labelAllContactGroupsSelected;

  const deleteItem = ({ option, value }): void => {
    const newItems = reject(propEq(option.id, 'id'), value);
    setFieldValue(valueName, newItems);
  };

  const getEndpoint = (): string =>
    equals(type, 'contacts') ? findContactsEndpoint : findContactGroupsEndpoint;

  const checkCheckBox = (event: ChangeEvent<HTMLInputElement>): void =>
    equals(type, 'contacts')
      ? setAllContactsSelected(event.target.checked)
      : setAllContactGroupsSelected(event.target.checked);

  const onCheckboxChange = (event: ChangeEvent<HTMLInputElement>): void => {
    checkCheckBox(event);
    setFieldValue(allValueName, checked);
    setFieldValue(valueName, []);
    setFieldTouched(valueName, true, false);
  };

  const onMultiselectChange = () => (_, value: Array<NamedEntity>) => {
    setFieldValue(valueName, value);
  };

  return {
    checked,
    deleteItem,
    getEndpoint,
    label,
    labelAll,
    labelAllSelected,
    onCheckboxChange,
    onMultiselectChange,
    value: equals(type, 'contacts') ? values.contacts : values.contactGroups
  };
};

export default useMultiConnectedAutocomplete;
