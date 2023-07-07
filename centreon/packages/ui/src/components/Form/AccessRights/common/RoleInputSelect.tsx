import { ReactElement, useCallback } from 'react';

import { MenuItem as MuiMenuItem, Select as MuiSelect } from '@mui/material';

import { useAccessRightsForm } from '../useAccessRightsForm';
import { RoleResource } from '../AccessRights.resource';

import { useStyles } from './RoleInputSelect.styles';
import { useInputStyles } from './Input.styles';

type RoleInputSelectProps = {
  disabled?: boolean;
  id: string;
  initialValue?: string;
  label?: string;
  name: string;
  onChange?: (value: RoleResource['role']) => void;
};

const RoleInputSelect = ({
  label,
  initialValue,
  onChange,
  disabled,
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
      defaultValue={initialValue}
      size="small"
      {...props}
      {...(label && { label })}
      MenuProps={{
        className: inputClasses.inputDropdown
      }}
      className={classes.roleInputSelect}
      data-testid="role-input"
      disabled={disabled}
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
