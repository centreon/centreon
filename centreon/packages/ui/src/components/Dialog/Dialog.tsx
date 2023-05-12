import {
  Dialog as MuiDialog,
  DialogProps as MuiDialogProps
} from '@mui/material';

import { useDialogStyles } from './Dialog.styles';

type DialogProps = MuiDialogProps & {
  onClose?: (event: object, reason: 'escapeKeyDown' | 'backdropClick') => void;
};

/** *
 * @description This component is *WIP* and is not ready for production. Use the default `Dialog` component instead.
 */
const Dialog = ({ children, ...dialogProps }: DialogProps): JSX.Element => {
  const { classes } = useDialogStyles();

  return (
    <MuiDialog className={classes.dialog} {...dialogProps}>
      {children}
    </MuiDialog>
  );
};

export { Dialog };
