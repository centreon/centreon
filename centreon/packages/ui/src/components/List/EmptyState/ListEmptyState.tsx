import React from 'react';

import { Add as AddIcon } from '@mui/icons-material';

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

  return (
    <div className={classes.listEmptyState}>
      <h2>{labels.title}</h2>
      <div className={classes.actions}>
        <Button
          dataTestId={dataTestId}
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
