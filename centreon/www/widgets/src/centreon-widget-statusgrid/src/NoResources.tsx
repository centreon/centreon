import { useTranslation } from 'react-i18next';

import { Box, Typography } from '@mui/material';

import { useNoResourcesStyles } from './StatusGrid.styles';
import { labelNoResources } from './translatedLabels';

const NoResources = (): JSX.Element => {
  const { classes } = useNoResourcesStyles();
  const { t } = useTranslation();

  return (
    <Box className={classes.noDataFound}>
      <Typography variant="h5">{t(labelNoResources)}</Typography>
    </Box>
  );
};

export default NoResources;
