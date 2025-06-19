import { ReactElement, ReactNode } from 'react';

import { DialogTitleProps, DialogTitle as MuiDialogTitle } from '@mui/material';

import '../../../src/ThemeProvider/tailwindcss.css';

import { modalHeader } from './modal.module.css';

export type ModalHeaderProps = {
  children?: ReactNode;
};

const ModalHeader = ({
  children,
  ...rest
}: ModalHeaderProps & DialogTitleProps): ReactElement => {
  return (
    <div className={modalHeader}>
      <MuiDialogTitle className="p-0 font-bold" color="primary" {...rest}>
        {children}
      </MuiDialogTitle>
    </div>
  );
};

export { ModalHeader };
