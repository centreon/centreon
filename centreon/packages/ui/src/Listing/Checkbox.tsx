import {
  useTheme,
  Checkbox as MuiCheckbox,
  CheckboxProps
} from '@mui/material';

const Checkbox = (
  props: Omit<CheckboxProps, 'size' | 'color'>
): JSX.Element => {
  const theme = useTheme();

  return (
    <MuiCheckbox
      color="primary"
      size="small"
      style={{ padding: theme.spacing(0.5) }}
      {...props}
    />
  );
};

export default Checkbox;
