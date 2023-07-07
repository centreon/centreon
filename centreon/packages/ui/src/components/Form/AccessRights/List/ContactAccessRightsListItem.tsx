import { ReactElement, useMemo } from 'react';

import RotateRightIcon from '@mui/icons-material/RotateRight';
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutline';
import { Chip as MuiChip } from '@mui/material';

import { IconButton } from '../../../Button';
import { List } from '../../../List';
import {
  ContactAccessRightState,
  ContactAccessRightStateResource,
  isContactResource
} from '../AccessRights.resource';
import { useAccessRightsForm } from '../useAccessRightsForm';
import { RoleInputSelect } from '../common/RoleInputSelect';
import { GroupLabel } from '../common/GroupLabel';

import { useStyles } from './ContactAccessRightsList.styles';

export type ContactAccessRightsListItemProps = {
  hasAvatar?: boolean;
  labels: {
    group: string;
    state: Record<Exclude<ContactAccessRightState, 'unchanged'>, string>;
  };
  resource: ContactAccessRightStateResource;
};

const ContactAccessRightsListItem = ({
  labels,
  resource: { contactAccessRight, state },
  hasAvatar = false
}: ContactAccessRightsListItemProps): ReactElement => {
  const { classes } = useStyles();
  const { updateContactAccessRight, removeContactAccessRight } =
    useAccessRightsForm();

  const isRemoved = useMemo(() => state === 'removed', [state]);

  const updateRole = (e): void => {
    updateContactAccessRight({
      ...contactAccessRight,
      role: e.target.value
    });
  };
  const toggleRemoved = (): void => {
    removeContactAccessRight(contactAccessRight, isRemoved);
  };

  const getNameInitials = (name: string): string =>
    name
      .split(' ')
      .map((n) => n.charAt(0).toUpperCase())
      .slice(0, 2)
      .join('');

  return (
    <List.Item
      action={
        <IconButton
          data-testid={isRemoved ? 'restore_user' : 'remove_user'}
          icon={isRemoved ? <RotateRightIcon /> : <DeleteOutlineIcon />}
          variant="ghost"
          onClick={toggleRemoved}
        />
      }
      className={classes.contactAccessRightsListItem}
      data-is-removed={isRemoved}
      data-state={state}
      key={contactAccessRight.contact?.id}
    >
      <span>
        {hasAvatar && (
          <List.Item.Avatar>
            {getNameInitials(contactAccessRight.contact?.name || '')}
          </List.Item.Avatar>
        )}
        <List.Item.Text
          primaryText={
            contactAccessRight.contact && (
              <>
                {contactAccessRight.contact.name}{' '}
                {!isContactResource(contactAccessRight.contact) && (
                  <GroupLabel label={labels.group} />
                )}
              </>
            )
          }
          secondaryText={
            isContactResource(contactAccessRight.contact)
              ? contactAccessRight.contact?.email
              : undefined
          }
        />
      </span>

      {state !== 'unchanged' && (
        <MuiChip
          data-state={state}
          label={labels.state[state]}
          size="small"
          variant="outlined"
        />
      )}
      <RoleInputSelect
        disabled={isRemoved}
        id={`role-${contactAccessRight.contact?.id}`}
        initialValue={contactAccessRight.role}
        name={`role-${contactAccessRight.contact?.id}`}
        onChange={updateRole}
      />
    </List.Item>
  );
};

export { ContactAccessRightsListItem };
