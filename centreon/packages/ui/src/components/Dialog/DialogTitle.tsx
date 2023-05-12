import { ReactNode } from 'react';

import {
  DialogTitle as MuiDialogTitle,
  DialogTitleProps as MuiDialogTitleProps
} from '@mui/material';

import { useDialogTitleStyles } from './Dialog.styles';

interface DialogTitleProps extends MuiDialogTitleProps {
  children: ReactNode;
}

const DialogTitle = ({
  children,
  ...dialogTitleProps
}: DialogTitleProps): JSX.Element => {
  const { classes } = useDialogTitleStyles();

  return (
    <MuiDialogTitle {...dialogTitleProps} className={classes.dialogTitle}>
      {children}
    </MuiDialogTitle>
  );
};

export { DialogTitle };
