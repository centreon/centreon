import { Close as CloseIcon } from '@mui/icons-material';
import { Dialog as MuiDialog, Slide } from '@mui/material';

import { equals } from 'ramda';
import type React from 'react';
import type { ReactElement } from 'react';

import type { AriaLabelingAttributes } from '../../@types/aria-attributes';
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
      className={`${classes.modal} gap-6`}
      data-size={size}
      onClose={onClose}
      open={open}
      TransitionComponent={isFullscreen ? Slide : undefined}
      TransitionProps={{
        direction: 'up'
      }}
      {...attr}
    >
      {hasCloseButton && (
        <div className="absolute top-2 right-3 opacity-60">
          <IconButton
            aria-label="close"
            icon={<CloseIcon />}
            onClick={(e) => onClose?.(e, 'closeButton')}
            size="small"
            variant="ghost"
          />
        </div>
      )}
      {children}
    </MuiDialog>
  );
};

export { Modal };
