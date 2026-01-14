import { Button, ButtonProps } from '@mui/material';

const ActionButton = (props: ButtonProps): JSX.Element => (
  <Button color="primary" size="medium" {...props} />
);

export default ActionButton;
