import { useTranslation } from 'react-i18next';

import { Box, Typography } from '@mui/material';

import Listing from './Listing/Listing';
import { labelAdditionalConnectorConfiguration } from './translatedLabels';
import { useStyles } from './Page.styles';
import AdditionalConnectorModal from './Modal/Modal';
import CloseModalConfirmationDialog from './Modal/CloseModalConfirmation';

const AdditionalConnectorConfiguration = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  return (
    <Box className={classes.page}>
      <Typography
        area-label={t(labelAdditionalConnectorConfiguration)}
        className={classes.pageHeader}
        variant="h5"
      >
        {t(labelAdditionalConnectorConfiguration)}
      </Typography>
      <Box className={classes.listing}>
        <Listing />
        <AdditionalConnectorModal />
      </Box>
      <CloseModalConfirmationDialog />
    </Box>
  );
};

export default AdditionalConnectorConfiguration;
