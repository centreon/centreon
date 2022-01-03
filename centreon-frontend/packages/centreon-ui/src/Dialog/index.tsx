import React from 'react';

import {
  Button,
  Dialog as MuiDialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  DialogProps,
  CircularProgress,
} from '@mui/material';

export type Props = {
  children: React.ReactNode;
  confirmDisabled?: boolean;
  contentWidth?: number;
  labelCancel?: string;
  labelConfirm?: string;
  labelTitle?: string;
  onCancel?: () => void;
  onClose?: () => void;
  onConfirm: () => void;
  open: boolean;
  submitting?: boolean;
} & DialogProps;

const Dialog = ({
  open,
  onClose,
  onCancel,
  onConfirm,
  labelTitle,
  labelCancel = 'Cancel',
  labelConfirm = 'Confirm',
  children,
  contentWidth,
  confirmDisabled = false,
  submitting = false,
  ...rest
}: Props): JSX.Element => (
  <MuiDialog open={open} onClose={onClose} {...rest}>
    {labelTitle && <DialogTitle>{labelTitle}</DialogTitle>}
    {children && (
      <DialogContent style={{ overflowY: 'visible', width: contentWidth }}>
        {children}
      </DialogContent>
    )}
    <DialogActions>
      {onCancel && (
        <Button color="primary" onClick={onCancel}>
          {labelCancel}
        </Button>
      )}
      <Button
        color="primary"
        disabled={confirmDisabled}
        endIcon={submitting && <CircularProgress size={15} />}
        onClick={onConfirm}
      >
        {labelConfirm}
      </Button>
    </DialogActions>
  </MuiDialog>
);

export default Dialog;
