import type React from 'react';
import type { ReactElement } from 'react';

import { Button } from '../Button';
import { useStyles } from './Modal.styles';

export type ModalActionsProps = {
  children?: React.ReactNode;
  disabled?: boolean;
  isDanger?: boolean;
  isFixed?: boolean;
  labels?: ModalActionsLabels;
  onCancel?: () => void;
  onConfirm?: () => void;
};

export type ModalActionsLabels = {
  cancel: string;
  confirm: string;
};

const ModalActions = ({
  children,
  labels,
  onCancel,
  onConfirm,
  isDanger = false,
  disabled,
  isFixed
}: ModalActionsProps): ReactElement => {
  const { classes } = useStyles();

  return (
    <div className={classes.modalActions} data-fixed={isFixed}>
      {children || (
        <>
          <Button
            aria-label={labels?.cancel}
            data-testid="cancel"
            onClick={() => onCancel?.()}
            size="small"
            variant="secondary"
          >
            {labels?.cancel}
          </Button>
          <Button
            aria-label={labels?.confirm}
            data-testid="confirm"
            disabled={disabled}
            isDanger={isDanger}
            onClick={() => onConfirm?.()}
            size="small"
            type="submit"
            variant="primary"
          >
            {labels?.confirm}
          </Button>
        </>
      )}
    </div>
  );
};

export { ModalActions };
