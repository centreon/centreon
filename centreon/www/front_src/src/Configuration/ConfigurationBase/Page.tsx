import { usePluralizedTranslation } from '@centreon/ui';
import { Box, Typography, capitalize } from '@mui/material';
import { ConfigurationBase } from '../models';
import { Listing } from './Listing';
import { Modal } from './Modal';

import { DeleteDialog, DuplicateDialog } from './Dialogs';
import Filters from './Filters';
import { useStyles } from './Page.styles';

const Page = ({
  columns,
  resourceType,
  form
}: ConfigurationBase): JSX.Element => {
  const { classes } = useStyles();
  const { pluralizedT } = usePluralizedTranslation();

  const labelTitle = pluralizedT({
    label: capitalize(resourceType),
    count: 10
  });

  return (
    <>
      <Box className={classes.page}>
        <Box className={classes.pageHeader}>
          <Typography
            area-label={labelTitle}
            className={classes.title}
            variant="h5"
          >
            {labelTitle}
          </Typography>
          <Box className={classes.searchBar}>
            <Filters />
          </Box>
        </Box>
        <Box className={classes.listing}>
          <Listing columns={columns} />
          <Modal form={form} />
        </Box>
      </Box>
      <DeleteDialog />
      <DuplicateDialog />
    </>
  );
};

export default Page;
