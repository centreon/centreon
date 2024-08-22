import { useTranslation } from 'react-i18next';

import { Box, Typography } from '@mui/material';

import Filter from './Filter';
import usePageHeaderStyles from './PageHeader.styles';
import { labelResourceAccessRules } from './translatedLabels';

const Title = (): JSX.Element => {
  const { classes } = usePageHeaderStyles();
  const { t } = useTranslation();

  return (
    <Typography className={classes.title} variant="h5">
      {t(labelResourceAccessRules)}
      <div id="ceip_badge" />
    </Typography>
  );
};

const PageHeader = (): JSX.Element => {
  const { classes } = usePageHeaderStyles();

  return (
    <Box className={classes.box}>
      <Title />
      <Filter />
    </Box>
  );
};

export default PageHeader;
