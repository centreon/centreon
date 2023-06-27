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
    // We use both this additional class and the MUI one to increase specificity
    // MUI override the padding of this element using a selector of type .title + .content
    // so we need an higher specificity selector
    '&.MuiDialogContent-root': {
      paddingTop: theme.spacing(1)
    },
    width: contentWidth
  }
}));

interface DialogDataTestId {
  dataTestIdCanceledButton?: string;
  dataTestIdConfirmButton?: string;
}

export type Props = {
  cancelDisabled?: boolean;
  children?: ReactNode;
  className?: string;
  confirmDisabled?: boolean;
  contentWidth?: number;
  dataTestId?: DialogDataTestId;
  dialogActionsClassName?: string;
  dialogConfirmButtonClassName?: string;
  dialogContentClassName?: string;
  dialogPaperClassName?: string;
  dialogTitleClassName?: string;
  labelCancel?: string | null;
  labelConfirm?: string | null;
  labelTitle?: string | null;
  onCancel?: () => void;
  onClose?: () => void;
  onConfirm: (event, value?) => void;
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
  dataTestId,
  dialogPaperClassName,
  dialogTitleClassName,
  dialogContentClassName,
  dialogActionsClassName,
  dialogConfirmButtonClassName,
  ...rest
}: Props): JSX.Element => {
  const { classes, cx } = useStyles({ contentWidth });

  const dataTestIdConfirmButton =
    dataTestId?.dataTestIdConfirmButton ?? labelConfirm;

  const dataTestIdCanceledButton =
    dataTestId?.dataTestIdCanceledButton ?? labelCancel;

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
          <Button
            color="primary"
            data-testid={dataTestIdCanceledButton}
            disabled={cancelDisabled}
            onClick={onCancel}
          >
            {labelCancel}
          </Button>
        )}
        <Button
          aria-label={labelConfirm || ''}
          className={dialogConfirmButtonClassName}
          color="primary"
          data-testid={dataTestIdConfirmButton}
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
