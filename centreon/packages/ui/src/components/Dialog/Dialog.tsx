import React from 'react';
import { useStyles } from './Dialog.styles';
import { Dialog as MuiDialog, DialogProps as MuiDialogProps } from '@mui/material';

type DialogProps = MuiDialogProps & {
  onClose?: (event: object, reason: 'escapeKeyDown' | 'backdropClick') => void;
}

/***
 * @description This component is *WIP* and is not ready for production. Use the default `Dialog` component instead.
 */
const Dialog: React.FC<DialogProps> = ({
  children,
  ...dialogProps
}): JSX.Element => {
  const {classes} = useStyles();

  return (
    <MuiDialog
      className={classes.dialog}
      {...dialogProps}
    >
      {children}
    </MuiDialog>
  );
};

export { Dialog };