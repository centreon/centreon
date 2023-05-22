import React from 'react';

import { Button } from '../Button';

import { useStyles } from './Modal.styles';

export type ModalActionsProps = {
  children?: React.ReactNode;
  isDanger?: boolean;
  labels?: ModalActionsLabels;
  onCancel?: () => void;
  onConfirm?: () => void;
};

export type ModalActionsLabels = {
  cancel: string;
  confirm: string;
};

const ModalActions: React.FC<ModalActionsProps> = ({
  children,
  labels,
  onCancel,
  onConfirm,
  isDanger = false
}): JSX.Element => {
  const { classes } = useStyles();

  return (
    <div className={classes.modalActions}>
      {children || (
        <>
          <Button
            aria-label="cancel"
            size="small"
            variant="secondary"
            onClick={() => onCancel?.()}
          >
            {labels?.cancel}
          </Button>
          <Button
            aria-label="confirm"
            isDanger={isDanger}
            size="small"
            type="submit"
            variant="primary"
            onClick={() => onConfirm?.()}
          >
            {labels?.confirm}
          </Button>
        </>
      )}
    </div>
  );
};

export { ModalActions };
