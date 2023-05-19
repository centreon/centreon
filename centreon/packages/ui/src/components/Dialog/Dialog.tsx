import React from 'react';

import { Dialog as MuiDialog } from '@mui/material';

import { useStyles } from './Dialog.styles';

type DialogProps = {
  children: React.ReactNode;
  onClose?: (event: object, reason: 'escapeKeyDown' | 'backdropClick') => void;
  open: boolean;
};

/** *
 * @description This component is *WIP* and is not ready for production. Use the default `Dialog` component instead.
 */
const Dialog: React.FC<DialogProps> = ({
  children,
  onClose,
  open
}): JSX.Element => {
  const { classes } = useStyles();

  return (
    <MuiDialog className={classes.dialog} open={open} onClose={onClose}>
      {children}
    </MuiDialog>
  );
};

export { Dialog };
