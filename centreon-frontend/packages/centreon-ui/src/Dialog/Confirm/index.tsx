import DialogContentText from '@mui/material/DialogContentText';

import Dialog, { Props as DialogProps } from '..';

type Props = DialogProps & { labelMessage: string };

const Confirm = ({ labelMessage, ...rest }: Props): JSX.Element => (
  <Dialog {...rest}>
    {labelMessage && <DialogContentText>{labelMessage}</DialogContentText>}
  </Dialog>
);

export default Confirm;
