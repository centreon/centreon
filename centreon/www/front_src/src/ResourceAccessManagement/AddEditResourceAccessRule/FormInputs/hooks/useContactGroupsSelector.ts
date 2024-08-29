import { ChangeEvent } from 'react';

import { useFormikContext } from 'formik';
import { useSetAtom } from 'jotai';
import { propEq, reject } from 'ramda';

import { buildListingEndpoint } from '@centreon/ui';

import { allContactGroupsSelectedAtom } from '../../../atom';
import { NamedEntity, ResourceAccessRule } from '../../../models';
import { findContactGroupsEndpoint } from '../../api/endpoints';

interface UseContactGroupssSelectorState {
  checked: boolean;
  contactGroups: Array<NamedEntity>;
  deleteContactGroupsItem: ({ contactGroups, option }) => void;
  getEndpoint: () => (parameters) => string;
  onCheckboxChange: (event: ChangeEvent<HTMLInputElement>) => void;
  onMultiSelectChange: () => (_, contactGroups: Array<NamedEntity>) => void;
}

const useContactGroupsSelector = (): UseContactGroupssSelectorState => {
  const { values, setFieldValue, setFieldTouched } =
    useFormikContext<ResourceAccessRule>();

  const setAllContactGroupsSelected = useSetAtom(allContactGroupsSelectedAtom);

  const deleteContactGroupsItem = ({ contactGroups, option }): void => {
    const newContactGroups = reject(propEq(option.id, 'id'), contactGroups);
    setFieldValue('contactGroups', newContactGroups);
  };

  const getEndpoint =
    () =>
    (parameters): string => {
      return buildListingEndpoint({
        baseEndpoint: findContactGroupsEndpoint,
        customQueryParameters: undefined,
        parameters
      });
    };

  const onCheckboxChange = (event: ChangeEvent<HTMLInputElement>): void => {
    setFieldValue('contactGroups', []);
    setFieldValue('allContactGroups', event.target.checked);
    setFieldTouched('contactGroups', true, false);
    setFieldTouched('allContactGroups', true, false);
    setAllContactGroupsSelected(event.target.checked);
  };

  const onMultiSelectChange = () => (_, contactGroups: Array<NamedEntity>) => {
    setFieldValue('contactGroups', contactGroups);
  };

  return {
    checked: values.allContactGroups,
    contactGroups: values.contactGroups,
    deleteContactGroupsItem,
    getEndpoint,
    onCheckboxChange,
    onMultiSelectChange
  };
};

export default useContactGroupsSelector;
