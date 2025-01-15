import { ReactNode } from 'react';

import { makeStyles } from 'tss-react/mui';

import {
  Button,
  ButtonProps,
  CircularProgress,
  DialogActions,
  DialogContent,
  DialogProps,
  DialogTitle,
  Dialog as MuiDialog
} from '@mui/material';

import { DataTestAttributes } from '../@types/data-attributes';

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

export type Props = {
  cancelDisabled?: boolean;
  children?: ReactNode;
  className?: string;
  confirmDisabled?: boolean;
  contentWidth?: number;
  dialogActionsClassName?: string;
  dialogConfirmButtonClassName?: string;
  dialogContentClassName?: string;
  dialogPaperClassName?: string;
  dialogTitleClassName?: string;
  labelCancel?: string | null;
  labelConfirm?: string | null;
  labelTitle?: ReactNode;
  onCancel?: () => void;
  onClose?: () => void;
  onConfirm: (event, value?) => void;
  open: boolean;
  restCancelButtonProps?: ButtonProps;
  restConfirmButtonProps?: ButtonProps;
  submitting?: boolean;
} & DialogProps &
  DataTestAttributes;

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
  dialogConfirmButtonClassName,
  restCancelButtonProps,
  restConfirmButtonProps,
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
          <Button
            color="primary"
            data-testid="Cancel"
            disabled={cancelDisabled}
            onClick={onCancel}
            {...restCancelButtonProps}
          >
            {labelCancel}
          </Button>
        )}
        <Button
          aria-label={labelConfirm || ''}
          className={dialogConfirmButtonClassName}
          color="primary"
          data-testid="Confirm"
          disabled={confirmDisabled}
          endIcon={submitting && <CircularProgress size={15} />}
          onClick={onConfirm}
          {...restConfirmButtonProps}
        >
          {labelConfirm}
        </Button>
      </DialogActions>
    </MuiDialog>
  );
};

export default Dialog;
