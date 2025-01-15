import { Dispatch, SetStateAction, useEffect, useState } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';
import { equals, includes, isNil } from 'ramda';

import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import { ListItemText, MenuItem } from '@mui/material';

import { SelectEntry, buildListingEndpoint } from '../../../..';
import {
  accessRightIdsDerivedAtom,
  addAccessRightDerivedAtom,
  contactTypeAtom
} from '../atoms';
import { AccessRightInitialValues, ContactType, Endpoints } from '../models';

interface UseShareInputState {
  add: () => void;
  changeIdValue: (entry: SelectEntry) => void;
  getEndpoint: (parameters) => string;
  getOptionDisabled: (option) => boolean;
  isContactGroup: boolean;
  renderOption: (attr, option) => JSX.Element;
  selectContact: (_, entry) => void;
  selectedContact: AccessRightInitialValues | null;
  selectedRole: string;
  setSelectedRole: Dispatch<SetStateAction<string>>;
}

const useShareInput = (endpoints: Endpoints): UseShareInputState => {
  const [selectedContact, setSelectedContact] =
    useState<AccessRightInitialValues | null>(null);
  const [selectedRole, setSelectedRole] = useState('viewer');

  const accessRightIds = useAtomValue(accessRightIdsDerivedAtom);
  const contactType = useAtomValue(contactTypeAtom);
  const addAccessRight = useSetAtom(addAccessRightDerivedAtom);

  const isContactGroup = equals(contactType, ContactType.ContactGroup);

  const selectContact = (_, entry): void => {
    setSelectedContact(entry);
    if (equals('editor', entry.most_permissive_role)) {
      return;
    }
    setSelectedRole('viewer');
  };

  const add = (): void => {
    if (isNil(selectedContact)) {
      return;
    }

    addAccessRight({
      email: selectedContact.email,
      id: selectedContact.id,
      isContactGroup,
      name: selectedContact.name,
      role: selectedRole
    });

    setSelectedContact(null);
  };

  const getEndpoint = (parameters): string =>
    buildListingEndpoint({
      baseEndpoint: isContactGroup ? endpoints.contactGroup : endpoints.contact,
      parameters: {
        ...parameters,
        sort: { name: 'ASC' }
      }
    });

  const renderOption = (attr, option): JSX.Element => {
    return (
      <MenuItem {...attr}>
        <ListItemText>{option.name}</ListItemText>
        {includes(option.id, accessRightIds) && (
          <CheckCircleIcon color="success" />
        )}
      </MenuItem>
    );
  };

  const getOptionDisabled = (option): boolean => {
    return includes(option.id, accessRightIds);
  };

  const changeIdValue = (item: SelectEntry): string => {
    return `${
      isContactGroup ? ContactType.ContactGroup : ContactType.Contact
    }_${item.id}`;
  };

  useEffect(() => {
    setSelectedContact(null);
  }, [contactType]);

  return {
    add,
    changeIdValue,
    getEndpoint,
    getOptionDisabled,
    isContactGroup,
    renderOption,
    selectContact,
    selectedContact,
    selectedRole,
    setSelectedRole
  };
};

export default useShareInput;
