import { ReactElement, ReactNode } from 'react';

import { modalBody } from './modal.module.css';

export type ModalHeaderProps = {
  children?: ReactNode;
};

const ModalBody = ({ children }: ModalHeaderProps): ReactElement => {
  return <div className={modalBody}>{children}</div>;
};

export { ModalBody };
