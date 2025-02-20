import { usePluralizedTranslation } from '@centreon/ui';
import { Box, Typography, capitalize } from '@mui/material';
import { DeleteDialog, DuplicateDialog } from './Dialogs';
import Filters from './Filters';
import { Listing } from './Listing';
import { Modal } from './Modal';

import { ConfigurationBase } from '../models';
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
        <Box className={classes.pageBody}>
          <Listing columns={columns} />
        </Box>
      </Box>

      <Modal form={form} />
      <DeleteDialog />
      <DuplicateDialog />
    </>
  );
};

export default Page;
