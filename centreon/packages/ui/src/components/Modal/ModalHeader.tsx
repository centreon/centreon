import { ReactElement, ReactNode } from 'react';

import { DialogTitleProps, Typography } from '@mui/material';

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
      <Typography variant="h6" color="primary" {...rest}>
        {children}
      </Typography>
    </div>
  );
};

export { ModalHeader };
