import {
  ButtonProps,
  DialogContentText,
  DialogContentTextProps,
  Typography
} from '@mui/material';

import Dialog, { Props as DialogProps } from '..';

type Props = DialogProps & {
  children?: JSX.Element;
  labelMessage?: string | null;
  labelSecondMessage?: string | null;
  restCancelButtonProps?: ButtonProps;
  restConfirmButtonProps?: ButtonProps;
  dialogContentTextProps?: DialogContentTextProps;
};

const Confirm = ({
  labelMessage,
  labelSecondMessage,
  children,
  restCancelButtonProps,
  restConfirmButtonProps,
  dialogContentTextProps,
  ...rest
}: Props): JSX.Element => (
  <Dialog
    restCancelButtonProps={restCancelButtonProps}
    restConfirmButtonProps={restConfirmButtonProps}
    {...rest}
  >
    <DialogContentText {...dialogContentTextProps}>
      {labelMessage && <Typography>{labelMessage}</Typography>}
      {labelSecondMessage && <Typography>{labelSecondMessage}</Typography>}
      {children}
    </DialogContentText>
  </Dialog>
);

export default Confirm;
