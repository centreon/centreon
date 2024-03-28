import { ReactElement } from 'react';

import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { labelAddResourceDatasets } from '../../../translatedLabels';
import { useResourceSelectionStyles } from '../styles/ResourceSelection.styles';

import DatasetFilters from './DatasetFilters';

const ResourceSelection = (): ReactElement => {
  const { t } = useTranslation();
  const { classes } = useResourceSelectionStyles();

  return (
    <div className={classes.resourceSelectionContainter}>
      <div>
        <Typography className={classes.resourceSelectionTitle}>
          {t(labelAddResourceDatasets)}
        </Typography>
      </div>
      <DatasetFilters />
    </div>
  );
};

export default ResourceSelection;
