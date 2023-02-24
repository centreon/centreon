import { DialogContentText, Typography } from '@mui/material';

import Dialog, { Props as DialogProps } from '..';

type Props = DialogProps & {
  labelMessage?: string | null;
  labelSecondMessage?: string | null;
};

const Confirm = ({
  labelMessage,
  labelSecondMessage,
  ...rest
}: Props): JSX.Element => (
  <Dialog {...rest}>
    <DialogContentText>
      {labelMessage && <Typography>{labelMessage}</Typography>}
      {labelSecondMessage && <Typography>{labelSecondMessage}</Typography>}
    </DialogContentText>
  </Dialog>
);

export default Confirm;
