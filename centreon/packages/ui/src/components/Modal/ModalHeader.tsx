import React, { ReactNode } from 'react';

import { DialogTitle as MuiDialogTitle } from '@mui/material';

import { useStyles } from './Modal.styles';

export type ModalHeaderProps = {
  children?: ReactNode;
};

const ModalHeader: React.FC<ModalHeaderProps> = ({ children }): JSX.Element => {
  const { classes } = useStyles();

  return (
    <div className={classes.modalHeader}>
      <MuiDialogTitle>{children}</MuiDialogTitle>
    </div>
  );
};

export { ModalHeader };
