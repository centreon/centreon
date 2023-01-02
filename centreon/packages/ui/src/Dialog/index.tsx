import { ReactNode } from 'react';

import { makeStyles } from 'tss-react/mui';

import {
  Button,
  Dialog as MuiDialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  DialogProps,
  CircularProgress
} from '@mui/material';

interface StylesProps {
  contentWidth?: number;
}

const useStyles = makeStyles<StylesProps>()((theme, { contentWidth }) => ({
  dialogContent: {
    width: contentWidth
  }
}));

export type Props = {
  cancelDisabled?: boolean;
  children?: ReactNode;
  className?: string;
  confirmDisabled?: boolean;
  contentWidth?: number;
  dialogActionsClassName?: string;
  dialogContentClassName?: string;
  dialogPaperClassName?: string;
  dialogTitleClassName?: string;
  labelCancel?: string;
  labelConfirm?: string;
  labelTitle?: string;
  onCancel?: () => void;
  onClose?: () => void;
  onConfirm: (event) => void;
  open: boolean;
  submitting?: boolean;
} & DialogProps;

const Dialog = ({
  open,
  onClose,
  onCancel,
  onConfirm,
  labelTitle = 'Are you sure?',
  labelCancel = 'Cancel',
  labelConfirm = 'Confirm',
  children,
  contentWidth,
  confirmDisabled = false,
  cancelDisabled = false,
  submitting = false,
  dialogPaperClassName,
  dialogTitleClassName,
  dialogContentClassName,
  dialogActionsClassName,
  ...rest
}: Props): JSX.Element => {
  const { classes, cx } = useStyles({ contentWidth });

  return (
    <MuiDialog
      PaperProps={{
        className: dialogPaperClassName
      }}
      open={open}
      scroll="paper"
      onClose={onClose}
      {...rest}
    >
      {labelTitle && (
        <DialogTitle className={dialogTitleClassName}>{labelTitle}</DialogTitle>
      )}
      {children && (
        <DialogContent
          className={cx(classes.dialogContent, dialogContentClassName)}
        >
          {children}
        </DialogContent>
      )}
      <DialogActions className={dialogActionsClassName}>
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
};

export default Dialog;
