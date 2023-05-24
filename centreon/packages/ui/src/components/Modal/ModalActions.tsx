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
            aria-label="cancel"
            disabled={isLoading}
            size="small"
            variant="secondary"
            onClick={() => onCancel?.()}
          >
            {labels?.cancel}
          </Button>
          <SaveButton
            aria-label="confirm"
            color={isDanger ? 'error' : 'primary'}
            labelLoading={labels?.loading}
            labelSave={labels?.confirm}
            loading={isLoading}
            size="small"
            type="submit"
            onClick={() => onConfirm?.()}
          />
        </>
      )}
    </div>
  );
};

export { ModalActions };
