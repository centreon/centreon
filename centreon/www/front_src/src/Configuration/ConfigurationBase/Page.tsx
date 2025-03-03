import { usePluralizedTranslation } from '@centreon/ui';
import { Box, capitalize } from '@mui/material';
import { ConfigurationBase } from '../models';

import { PageHeader, PageLayout } from '@centreon/ui/components';
import { DeleteDialog, DuplicateDialog } from './Dialogs';
import Filters from './Filters';
import { Listing } from './Listing';
import { Modal } from './Modal';
import { useStyles } from './Page.styles';

const Page = ({
  columns,
  Form,
  resourceType
}: ConfigurationBase): JSX.Element => {
  const { classes } = useStyles();

  const { pluralizedT } = usePluralizedTranslation();

  const labelTitle = pluralizedT({
    label: capitalize(resourceType),
    count: 10
  });

  return (
    <PageLayout>
      <PageLayout.Header>
        <PageHeader>
          <PageHeader.Main>
            <PageHeader.Title title={labelTitle} />
          </PageHeader.Main>
          <PageHeader.Actions>
            <Box className={classes.searchBar}>
              <Filters />
            </Box>
          </PageHeader.Actions>
        </PageHeader>
      </PageLayout.Header>
      <PageLayout.Body>
        <Box className={classes.listing}>
          <Listing columns={columns} />
          <Modal Form={Form} />
        </Box>
      </PageLayout.Body>

      <DeleteDialog />
      <DuplicateDialog />
    </PageLayout>
  );
};

export default Page;
