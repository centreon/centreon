import React, { ReactElement } from 'react';

import { useTranslation } from 'react-i18next';

import { Add as AddIcon } from '@mui/icons-material';
import { Typography } from '@mui/material';

import { Button } from '../../Button';

import { useStyles } from './DataTableEmptyState.styles';

type ListEmptyStateProps = {
  labels: {
    actions: {
      create: string;
    };
    title: string;
  };
  onCreate?: () => void;
};

const DataTableEmptyState = ({
  labels,
  onCreate
}: ListEmptyStateProps): ReactElement => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  return (
    <div className={classes.dataTableEmptyState}>
      <Typography variant="h4">{t(labels.title)}</Typography>
      <div className={classes.actions}>
        <Button
          aria-label="create"
          icon={<AddIcon />}
          iconVariant="start"
          onClick={() => onCreate?.()}
        >
          {t(labels.actions.create)}
        </Button>
      </div>
    </div>
  );
};

export { DataTableEmptyState };
