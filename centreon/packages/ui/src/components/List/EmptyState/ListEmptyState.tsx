import React from 'react';
import { useStyles } from './ListEmptyState.styles';
import { Button } from '../../Button';
import { Add as AddIcon } from '@mui/icons-material';


type ListEmptyStateProps = {
  labels: {
    title: string;
    actions: {
      create: string;
    }
  }
  onCreate?: () => void;
};

const ListEmptyState: React.FC<ListEmptyStateProps> = ({
  labels,
  onCreate
}): JSX.Element => {
  const {classes} = useStyles();

  return (
    <div
      className={classes.listEmptyState}
    >
      <h2>{labels.title}</h2>
      <div className={classes.actions}>
        <Button
          variant="primary"
          iconVariant="start"
          icon={<AddIcon/>}
          onClick={() => onCreate?.()}
        >
          {labels.actions.create}
        </Button>
      </div>
    </div>
  );
};

export { ListEmptyState };