import { useTranslation } from 'react-i18next';

import { Box, Typography } from '@mui/material';

import Filter from './Filter';
import useStyle from './PageHeader.styles';
import { labelNotificationsManagement } from './translatedLabels';

const Title = (): JSX.Element => {
  const { classes } = useStyle();
  const { t } = useTranslation();

  return (
    <Typography className={classes.title} variant="h5">
      {t(labelNotificationsManagement)}
    </Typography>
  );
};

const PageHeader = (): JSX.Element => {
  const { classes } = useStyle();

  return (
    <Box className={classes.box}>
      <Title />
      <Filter />
    </Box>
  );
};

export default PageHeader;
