import { CheckboxProps, Checkbox as MuiCheckbox } from '@mui/material';

const Checkbox = ({
  className,
  ...props
}: { className?: string } & Omit<
  CheckboxProps,
  'size' | 'color'
>): JSX.Element => (
  <MuiCheckbox
    className={`p-0 ${className}`}
    color="primary"
    size="small"
    {...props}
  />
);

export default Checkbox;
