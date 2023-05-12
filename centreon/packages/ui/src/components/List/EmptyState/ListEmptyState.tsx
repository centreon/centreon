import React from 'react';

import { Add as AddIcon } from '@mui/icons-material';

import { Button } from '../../Button';

import { useStyles } from './ListEmptyState.styles';

interface ListEmptyStateProps {
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
  onCreate
}): JSX.Element => {
  const { classes } = useStyles();

  return (
    <div className={classes.listEmptyState}>
      <h2>{labels.title}</h2>
      <div className={classes.actions}>
        <Button
          icon={<AddIcon />}
          iconVariant="start"
          onClick={(): void => onCreate?.()}
        >
          {labels.actions.create}
        </Button>
      </div>
    </div>
  );
};

export { ListEmptyState };
