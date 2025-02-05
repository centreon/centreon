import { useTranslation } from 'react-i18next';

import { Box, Typography } from '@mui/material';

import Listing from './Listing/Listing';
import { useStyles } from './Page.styles';
import { labelHostGroups } from './translatedLabels';

const HostGroups = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  return (
    <Box className={classes.page}>
      <Typography
        area-label={t(labelHostGroups)}
        className={classes.pageHeader}
        variant="h5"
      >
        {t(labelHostGroups)}
      </Typography>
      <Box className={classes.listing}>
        <Listing />
      </Box>
    </Box>
  );
};

export default HostGroups;
