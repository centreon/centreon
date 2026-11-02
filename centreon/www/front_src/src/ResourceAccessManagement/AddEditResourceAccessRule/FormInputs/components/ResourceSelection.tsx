import { Typography } from '@mui/material';

import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { labelAddResourceDatasets } from '../../../translatedLabels';
import DatasetFilters from './DatasetFilters';

const ResourceSelection = (): ReactElement => {
  const { t } = useTranslation();

  return (
    <div className="flex flex-col gap-2">
      <div>
        <Typography className="pb-1 text-base font-bold">
          {t(labelAddResourceDatasets)}
        </Typography>
      </div>
      <DatasetFilters />
    </div>
  );
};

export default ResourceSelection;
