import { useSetAtom } from 'jotai';

import { Chip } from '@mui/material';

import { AccessRight, Labels } from '../models';
import { List } from '../../../List';
import RoleSelectField from '../common/RoleSelectField';
import { SelectEntry } from '../../../..';
import { updateContactRoleDerivedAtom } from '../atoms';

import { useItem } from './useItem';
import { useListStyles } from './List.styles';
import StateChip from './StateChip';
import RemoveAccessRight from './RemoveAccessRight';

interface Props extends AccessRight {
  index: number;
  labels: Labels['list'];
  roles: Array<SelectEntry>;
}

const Item = ({
  index,
  id,
  isAdded,
  isContactGroup,
  isRemoved,
  isUpdated,
  role,
  name,
  email,
  labels,
  roles
}: Props): JSX.Element => {
  const { classes } = useListStyles();
  const { initials, groupLabel, getState } = useItem({
    isAdded,
    isContactGroup,
    isRemoved,
    isUpdated,
    list: labels,
    name
  });
  const updateContactRole = useSetAtom(updateContactRoleDerivedAtom);

  const state = getState();

  const changeRole = (newRole: string): void => {
    updateContactRole({ id, index, newRole });
  };

  return (
    <List.Item
      action={
        <>
          {state && <StateChip {...state} />}
          <RoleSelectField
            disabled={isRemoved}
            roles={roles}
            testId={`role-${name}`}
            value={role}
            onChange={changeRole}
          />
          <RemoveAccessRight index={index} isRemoved={isRemoved} name={name} />
        </>
      }
      className={classes.item}
      data-isRemoved={isRemoved}
    >
      <List.Item.Avatar>{initials}</List.Item.Avatar>
      <div className={classes.itemNameAndGroup}>
        <List.Item.Text
          className={classes.name}
          primaryText={name}
          secondaryText={email}
        />
        {groupLabel && (
          <Chip
            data-groupChip={name}
            data-type="group-chip"
            label={groupLabel}
            size="small"
          />
        )}
      </div>
    </List.Item>
  );
};

export default Item;
