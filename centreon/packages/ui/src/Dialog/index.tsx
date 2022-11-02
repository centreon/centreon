import * as React from 'react';

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
  cancelDisabled?: boolean;
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
  cancelDisabled = false,
  submitting = false,
  ...rest
}: Props): JSX.Element => (
  <MuiDialog open={open} scroll="paper" onClose={onClose} {...rest}>
    {labelTitle && <DialogTitle>{labelTitle}</DialogTitle>}
    {children && (
      <DialogContent style={{ width: contentWidth }}>{children}</DialogContent>
    )}
    <DialogActions>
      {onCancel && (
        <Button color="primary" disabled={cancelDisabled} onClick={onCancel}>
          {labelCancel}
        </Button>
      )}
      <Button
        aria-label={labelConfirm}
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
