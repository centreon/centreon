import { SelectChangeEvent } from '@mui/material';

import SelectField, { SelectEntry } from '../../../../InputField/Select';

import { useRoleSelectField } from './RoleSelectField.styles';

interface Props {
  disabled?: boolean;
  label?: string;
  onChange: (newValue: string) => void;
  roles: Array<SelectEntry>;
  testId: string;
  value: string;
}

const RoleSelectField = ({
  roles,
  value,
  onChange,
  testId,
  label,
  disabled
}: Props): JSX.Element => {
  const { classes } = useRoleSelectField();
  const change = (event: SelectChangeEvent): void => {
    onChange(event.target.value as string);
  };

  return (
    <div className={classes.roleContainer}>
      <SelectField
        fullWidth
        dataTestId={testId}
        disabled={disabled}
        label={label}
        options={roles}
        selectedOptionId={value}
        size="small"
        onChange={change}
      />
    </div>
  );
};

export default RoleSelectField;
