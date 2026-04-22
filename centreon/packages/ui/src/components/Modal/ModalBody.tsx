import type { ReactElement, ReactNode } from 'react';

import styles from './modal.module.css';

export type ModalHeaderProps = {
  children?: ReactNode;
};

const ModalBody = ({ children }: ModalHeaderProps): ReactElement => {
  return (
    <div className={styles.modalBody} data-testid="modal-body">
      {children}
    </div>
  );
};

export { ModalBody };
