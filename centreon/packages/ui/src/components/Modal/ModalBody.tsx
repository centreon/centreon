import { ReactElement, ReactNode } from 'react';

import { useStyles } from './Modal.styles';

export type ModalHeaderProps = {
  children?: ReactNode;
};

const ModalBody = ({ children }: ModalHeaderProps): ReactElement => {
  const { classes } = useStyles();

  return <div className={classes.modalBody}>{children}</div>;
};

export { ModalBody };
