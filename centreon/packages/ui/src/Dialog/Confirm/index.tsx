import {
  ButtonProps,
  DialogContentText,
  Typography,
  TypographyProps
} from '@mui/material';

import Dialog, { Props as DialogProps } from '..';

type Props = DialogProps & {
  children?: JSX.Element;
  labelMessage?: string | null;
  labelMessageProps?: TypographyProps;
  labelSecondMessage?: string | null;
  restCancelButtonProps?: ButtonProps;
  restConfirmButtonProps?: ButtonProps;
};

const Confirm = ({
  labelMessage,
  labelSecondMessage,
  children,
  restCancelButtonProps,
  restConfirmButtonProps,
  labelMessageProps,
  ...rest
}: Props): JSX.Element => (
  <Dialog
    restCancelButtonProps={restCancelButtonProps}
    restConfirmButtonProps={restConfirmButtonProps}
    {...rest}
  >
    <DialogContentText>
      {labelMessage && (
        <Typography {...labelMessageProps}>{labelMessage}</Typography>
      )}
      {labelSecondMessage && <Typography>{labelSecondMessage}</Typography>}
      {children}
    </DialogContentText>
  </Dialog>
);

export default Confirm;
