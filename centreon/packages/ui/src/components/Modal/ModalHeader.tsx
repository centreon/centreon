import React, { ReactElement, ReactNode } from 'react';

import { DialogTitle as MuiDialogTitle } from '@mui/material';

import { useStyles } from './Modal.styles';

export type ModalHeaderProps = {
  children?: ReactNode;
};

const ModalHeader = ({ children }: ModalHeaderProps): ReactElement => {
  const { classes } = useStyles();

  return (
    <div className={classes.modalHeader}>
      <MuiDialogTitle color="primary">{children}</MuiDialogTitle>
    </div>
  );
};

export { ModalHeader };
