import CheckCircleIcon from '@mui/icons-material/CheckCircle';

import { useAtomValue, useSetAtom } from 'jotai';
import { equals, includes, isNil } from 'ramda';
import type React from 'react';
import {
  type Dispatch,
  type ReactElement,
  type SetStateAction,
  useEffect,
  useState
} from 'react';

import { buildListingEndpoint, type SelectEntry } from '../../../..';
import type { SearchParameter } from '../../../../api/buildListingEndpoint/models';
import {
  accessRightIdsDerivedAtom,
  addAccessRightDerivedAtom,
  contactTypeAtom
} from '../atoms';
import {
  type AccessRightInitialValues,
  ContactType,
  type Endpoints
} from '../models';

interface ShareInputOption {
  id: number | string;
  most_permissive_role?: 'editor' | 'viewer';
  name: string;
}

interface ShareInputEndpointParameters {
  page: number;
  search?: SearchParameter;
}

interface UseShareInputState {
  add: () => void;
  changeIdValue: (entry: SelectEntry) => string;
  getEndpoint: (parameters: ShareInputEndpointParameters) => string;
  getOptionDisabled: (option: SelectEntry) => boolean;
  isContactGroup: boolean;
  getRenderedOptionText: (option: SelectEntry) => ReactElement | string;
  selectContact: (
    _: React.SyntheticEvent,
    entry: AccessRightInitialValues | null | unknown
  ) => void;
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

  const selectContact = (
    _: React.SyntheticEvent,
    entry: AccessRightInitialValues | null | unknown
  ): void => {
    const value = entry as AccessRightInitialValues | null;
    setSelectedContact(value);
    if (equals('editor', value?.most_permissive_role)) {
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

  const getEndpoint = (parameters: ShareInputEndpointParameters): string =>
    buildListingEndpoint({
      baseEndpoint: isContactGroup ? endpoints.contactGroup : endpoints.contact,
      parameters: {
        ...parameters,
        sort: { name: 'ASC' }
      }
    });

  const getRenderedOptionText = (option: SelectEntry): ReactElement => {
    const value = option as ShareInputOption | undefined;

    return (
      <>
        {value?.name}
        {includes(value?.id, accessRightIds) && (
          <CheckCircleIcon color="success" />
        )}
      </>
    );
  };

  const getOptionDisabled = (option: SelectEntry): boolean => {
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
    getRenderedOptionText,
    isContactGroup,
    selectContact,
    selectedContact,
    selectedRole,
    setSelectedRole
  };
};

export default useShareInput;
