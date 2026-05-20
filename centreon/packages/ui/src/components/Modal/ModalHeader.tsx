import {
  type DialogTitleProps,
  DialogTitle as MuiDialogTitle
} from '@mui/material';

import type { ReactElement, ReactNode } from 'react';

import '../../../src/ThemeProvider/tailwindcss.css';

export type ModalHeaderProps = {
  children?: ReactNode;
};

const ModalHeader = ({
  children,
  ...rest
}: ModalHeaderProps & DialogTitleProps): ReactElement => {
  return (
    <div
      className="flex gap-4 justify-between [&_.MuiDialogTitle-root]:p-0"
      data-testid="modal-header"
    >
      <MuiDialogTitle
        className="p-0 font-bold text-2xl"
        color="primary"
        {...rest}
      >
        {children}
      </MuiDialogTitle>
    </div>
  );
};

export { ModalHeader };
