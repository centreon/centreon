import React, { ReactNode } from 'react';

import { useStyles } from './Modal.styles';

export type ModalHeaderProps = {
  children?: ReactNode;
};

const ModalBody: React.FC<ModalHeaderProps> = ({ children }): JSX.Element => {
  const { classes } = useStyles();

  return <div className={classes.modalBody}>{children}</div>;
};

export { ModalBody };
