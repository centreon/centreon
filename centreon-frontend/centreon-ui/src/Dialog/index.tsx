import React from 'react';

import {
  Button,
  Dialog as MuiDialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  DialogProps,
  CircularProgress,
} from '@material-ui/core';

export type Props = {
  open: boolean;
  onClose?: () => void;
  onCancel?: () => void;
  onConfirm: () => void;
  labelTitle?: string;
  labelCancel?: string;
  labelConfirm?: string;
  children: React.ReactNode;
  contentWidth?: number;
  confirmDisabled?: boolean;
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
        onClick={onConfirm}
        disabled={confirmDisabled}
        endIcon={submitting && <CircularProgress size={15} />}
      >
        {labelConfirm}
      </Button>
    </DialogActions>
  </MuiDialog>
);

export default Dialog;
