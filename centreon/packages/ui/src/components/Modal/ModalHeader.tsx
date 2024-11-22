import { ReactElement, ReactNode } from 'react';

import { DialogTitleProps, DialogTitle as MuiDialogTitle } from '@mui/material';

import { useStyles } from './Modal.styles';

export type ModalHeaderProps = {
  children?: ReactNode;
};

const ModalHeader = ({
  children,
  ...rest
}: ModalHeaderProps & DialogTitleProps): ReactElement => {
  const { classes } = useStyles();

  return (
    <div className={classes.modalHeader}>
      <MuiDialogTitle color="primary" {...rest}>
        {children}
      </MuiDialogTitle>
    </div>
  );
};

export { ModalHeader };
