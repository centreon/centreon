import React, { ReactElement } from 'react';

import { equals } from 'ramda';

import { Close as CloseIcon } from '@mui/icons-material';
import { Dialog as MuiDialog, Slide } from '@mui/material';

import { AriaLabelingAttributes } from '../../@types/aria-attributes';
import { IconButton } from '../Button';

import { useStyles } from './Modal.styles';

export type ModalProps = {
  children: React.ReactNode;
  fullscreenMargins?: {
    bottom?: number;
    left?: number;
    right?: number;
    top?: number;
  };
  hasCloseButton?: boolean;
  onClose?: (
    event: object,
    reason: 'escapeKeyDown' | 'backdropClick' | 'closeButton'
  ) => void;
  open: boolean;
  size?: 'small' | 'medium' | 'large' | 'xlarge' | 'fullscreen';
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
  fullscreenMargins = {
    bottom: 0,
    left: 0,
    right: 0,
    top: 0
  },
  ...attr
}: ModalProps): ReactElement => {
  const { classes } = useStyles(fullscreenMargins);

  const isFullscreen = equals(size, 'fullscreen');

  return (
    <MuiDialog
      TransitionComponent={isFullscreen ? Slide : undefined}
      TransitionProps={{
        direction: 'up'
      }}
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
