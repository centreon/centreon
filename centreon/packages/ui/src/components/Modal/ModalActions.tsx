import React, { ReactElement } from 'react';

import { Button } from '../Button';
import { SaveButton } from '../..';

import { useStyles } from './Modal.styles';

export type ModalActionsProps = {
  children?: React.ReactNode;
  isDanger?: boolean;
  isLoading?: boolean;
  labels?: ModalActionsLabels;
  onCancel?: () => void;
  onConfirm?: () => void;
};

export type ModalActionsLabels = {
  cancel: string;
  confirm: string;
  loading: string;
};

const ModalActions = ({
  children,
  labels,
  onCancel,
  onConfirm,
  isDanger = false,
  isLoading = false
}: ModalActionsProps): ReactElement => {
  const { classes } = useStyles();

  return (
    <div className={classes.modalActions}>
      {children || (
        <>
          <Button
            aria-label={labels?.cancel}
            data-testid="cancel_confirmation"
            disabled={isLoading}
            size="small"
            variant="ghost"
            onClick={() => onCancel?.()}
          >
            {labels?.cancel}
          </Button>
          <SaveButton
            aria-label="confirm"
            color={isDanger ? 'error' : 'primary'}
            data-testid="confirm_confirmation"
            hasStartIcon={!isDanger}
            labelLoading={labels?.loading}
            labelSave={labels?.confirm}
            loading={isLoading}
            type="submit"
            onClick={() => onConfirm?.()}
          />
        </>
      )}
    </div>
  );
};

export { ModalActions };
