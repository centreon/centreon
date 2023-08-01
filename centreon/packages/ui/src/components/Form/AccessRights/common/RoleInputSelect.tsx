import { ReactElement, useCallback } from 'react';

import {
  MenuItem as MuiMenuItem,
  Select as MuiSelect,
  SelectProps as MuiSelectProps
} from '@mui/material';

import { useAccessRightsForm } from '../useAccessRightsForm';
import { RoleResource } from '../AccessRights.resource';

import { useStyles } from './RoleInputSelect.styles';
import { useInputStyles } from './Input.styles';

type RoleInputSelectProps = {
  id: string;
  label?: string;
  name: string;
  onChange?: (value: RoleResource['role']) => void;
} & Pick<MuiSelectProps, 'value' | 'defaultValue' | 'disabled'>;

const RoleInputSelect = ({
  label,
  onChange,
  ...props
}: RoleInputSelectProps): ReactElement => {
  const { classes } = useStyles();
  const { classes: inputClasses } = useInputStyles();

  const {
    options: { roles }
  } = useAccessRightsForm();

  const onInputChange = useCallback((e) => onChange?.(e), [onChange]);

  return (
    <MuiSelect
      size="small"
      {...props}
      {...(label && { label })}
      MenuProps={{
        className: inputClasses.inputDropdown
      }}
      className={classes.roleInputSelect}
      data-testid="role-input"
      onChange={onInputChange}
    >
      {roles.map(({ role }) => (
        <MuiMenuItem key={role} value={role}>
          {role}
        </MuiMenuItem>
      ))}
    </MuiSelect>
  );
};

export { RoleInputSelect };
