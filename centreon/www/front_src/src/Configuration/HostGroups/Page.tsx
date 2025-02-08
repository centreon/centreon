import { useTranslation } from 'react-i18next';

import { Box, Typography } from '@mui/material';

import Filters from './Listing/ActionsBar/Filters';
import Listing from './Listing/Listing';
import { useStyles } from './Page.styles';
import { labelHostGroups } from './translatedLabels';

const HostGroups = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  return (
    <Box className={classes.page}>
      <Box className={classes.pageHeader}>
        <Typography
          area-label={t(labelHostGroups)}
          className={classes.title}
          variant="h5"
        >
          {t(labelHostGroups)}
        </Typography>
        <Box className={classes.searchBar}>
          <Filters />
        </Box>
      </Box>
      <Box className={classes.listing}>
        <Listing />
      </Box>
    </Box>
  );
};

export default HostGroups;
