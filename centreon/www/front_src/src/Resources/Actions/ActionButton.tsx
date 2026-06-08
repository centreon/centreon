import { Button, ButtonProps } from '@mui/material';

const ActionButton = ({ sx, ...props }: ButtonProps): JSX.Element => (
  <Button
    color="primary"
    size="small"
    sx={{ whiteSpace: 'nowrap', ...sx }}
    {...props}
  />
);

export default ActionButton;
