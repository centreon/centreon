import type { ReactElement, ReactNode } from 'react';

export type ModalHeaderProps = {
  children?: ReactNode;
};

const ModalBody = ({ children }: ModalHeaderProps): ReactElement => {
  return (
    <div
      className="overflow-y-auto overflow-x-hidden h-full"
      data-testid="modal-body"
    >
      {children}
    </div>
  );
};

export { ModalBody };
