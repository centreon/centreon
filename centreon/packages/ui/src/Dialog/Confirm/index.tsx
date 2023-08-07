import { DialogContentText, Typography } from '@mui/material';

import Dialog, { Props as DialogProps } from '..';

type Props = DialogProps & {
  children?: JSX.Element;
  labelMessage?: string | null;
  labelSecondMessage?: string | null;
};

const Confirm = ({
  labelMessage,
  labelSecondMessage,
  children,
  ...rest
}: Props): JSX.Element => (
  <Dialog {...rest}>
    <DialogContentText>
      {labelMessage && <Typography>{labelMessage}</Typography>}
      {labelSecondMessage && <Typography>{labelSecondMessage}</Typography>}
      {children}
    </DialogContentText>
  </Dialog>
);

export default Confirm;
