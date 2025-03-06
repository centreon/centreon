import { ReactElement } from 'react';

import { useTranslation } from 'react-i18next';

import { Add as AddIcon } from '@mui/icons-material';
import { Typography as MuiTypography } from '@mui/material';

import { Button } from '../../Button';

import { useStyles } from './DataTableEmptyState.styles';

type ListEmptyStateProps = {
  canCreate?: boolean;
  labels: {
    actions?: {
      create: string;
    };
    title: string;
    description?: string;
  };
  onCreate?: () => void;
};

const DataTableEmptyState = ({
  labels,
  onCreate,
  canCreate = true
}: ListEmptyStateProps): ReactElement => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  return (
    <div
      className={classes.dataTableEmptyState}
      data-testid="data-table-empty-state"
    >
      <MuiTypography variant="h2">{t(labels.title)}</MuiTypography>
      {labels.description && (
        <MuiTypography className={classes.description}>
          {t(labels.description)}
        </MuiTypography>
      )}
      <div className={classes.actions}>
        {canCreate && (
          <Button
            aria-label="create"
            icon={<AddIcon />}
            iconVariant="start"
            onClick={() => onCreate?.()}
          >
            {t(labels.actions?.create || '')}
          </Button>
        )}
      </div>
    </div>
  );
};

export { DataTableEmptyState };
