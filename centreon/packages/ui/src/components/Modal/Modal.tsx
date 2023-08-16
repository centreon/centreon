import React, { ReactElement } from 'react';

import { Dialog as MuiDialog } from '@mui/material';
import { Close as CloseIcon } from '@mui/icons-material';

import { AriaLabelingAttributes } from '../../@types/aria-attributes';
import { IconButton } from '../Button';

import { useStyles } from './Modal.styles';

export type ModalProps = {
  children: React.ReactNode;
  hasCloseButton?: boolean;
  onClose?: (
    event: object,
    reason: 'escapeKeyDown' | 'backdropClick' | 'closeButton'
  ) => void;
  open: boolean;
  size?: 'small' | 'medium' | 'large' | 'xlarge';
} & AriaLabelingAttributes;

/** *
 * @description This component is *WIP* and is not ready for production. Use the default `Dialog` component instead.
 */
const Modal = ({
  children,
  hasCloseButton = true,
  onClose,
  open,
  size = 'small',
  ...attr
}: ModalProps): ReactElement => {
  const { classes } = useStyles();

  return (
    <MuiDialog
      className={classes.modal}
      data-size={size}
      open={open}
      onClose={onClose}
      {...attr}
    >
      {hasCloseButton && (
        <div className={classes.modalCloseButton}>
          <IconButton
            aria-label="close"
            icon={<CloseIcon />}
            size="small"
            variant="ghost"
            onClick={(e) => onClose?.(e, 'closeButton')}
          />
        </div>
      )}
      {children}
    </MuiDialog>
  );
};

export { Modal };
