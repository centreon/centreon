import React, { ReactNode } from 'react';

import { Close as CloseIcon } from '@mui/icons-material';
import { DialogTitle as MuiDialogTitle } from '@mui/material';

import { IconButton } from '../../Button';

import { useStyles } from './DialogHeader.styles';

type DialogHeaderProps = {
  children?: ReactNode;
  hasCloseButton?: boolean;
  onClose?: () => void;
};

const DialogHeader: React.FC<DialogHeaderProps> = ({
  children,
  hasCloseButton = false,
  onClose
}): JSX.Element => {
  const { classes } = useStyles();

  return (
    <div className={classes.dialogHeader}>
      <MuiDialogTitle>{children}</MuiDialogTitle>
      {hasCloseButton && (
        <IconButton
          aria-label="close"
          icon={<CloseIcon />}
          size="small"
          variant="ghost"
          onClick={() => onClose?.()}
        />
      )}
    </div>
  );
};

export { DialogHeader };
