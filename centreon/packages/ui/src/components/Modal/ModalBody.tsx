import { ReactElement, ReactNode } from 'react';

import style from './modal.module.css';

export type ModalHeaderProps = {
  children?: ReactNode;
};

const ModalBody = ({ children }: ModalHeaderProps): ReactElement => {
  return <div className={style.modalBody}>{children}</div>;
};

export { ModalBody };
