import React from 'react';

import { useTranslation } from 'react-i18next';

import { Add as AddIcon } from '@mui/icons-material';
import { Typography } from '@mui/material';

import { Button } from '../../Button';

import { useStyles } from './ListEmptyState.styles';

interface ListEmptyStateProps {
  dataTestId?: string;
  labels: {
    actions: {
      create: string;
    };
    title: string;
  };
  onCreate?: () => void;
}

const ListEmptyState: React.FC<ListEmptyStateProps> = ({
  labels,
  onCreate,
  dataTestId
}): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  return (
    <div className={classes.listEmptyState}>
      <Typography variant="h4">{t(labels.title)}</Typography>
      <div className={classes.actions}>
        <Button
          dataTestId={dataTestId}
          icon={<AddIcon />}
          iconVariant="start"
          onClick={(): void => onCreate?.()}
        >
          {t(labels.actions.create)}
        </Button>
      </div>
    </div>
  );
};

export { ListEmptyState };
