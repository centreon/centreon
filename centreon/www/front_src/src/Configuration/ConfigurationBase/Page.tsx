import { useTranslation } from 'react-i18next';

import { Box, Typography } from '@mui/material';
import { ConfigurationBase } from '../models';
import { Listing } from './Listing';
import Filters from './Listing/ActionsBar/Filters';
import { Modal } from './Modal';
import { useStyles } from './Page.styles';

const Page = ({
  columns,
  Form,
  resourceType
}: ConfigurationBase): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  return (
    <Box className={classes.page}>
      <Box className={classes.pageHeader}>
        <Typography
          area-label={t(resourceType)}
          className={classes.title}
          variant="h5"
        >
          {t(resourceType)}
        </Typography>
        <Box className={classes.searchBar}>
          <Filters />
        </Box>
      </Box>
      <Box className={classes.listing}>
        <Listing columns={columns} />
        <Modal Form={Form} />
      </Box>
    </Box>
  );
};

export default Page;
