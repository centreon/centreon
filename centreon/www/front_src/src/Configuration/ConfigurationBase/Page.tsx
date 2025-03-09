import { usePluralizedTranslation } from '@centreon/ui';
import { Box, capitalize } from '@mui/material';
import { DeleteDialog, DuplicateDialog } from './Dialogs';

import { PageHeader, PageLayout } from '@centreon/ui/components';
import { Listing } from './Listing';
import { Modal } from './Modal';

import { ConfigurationBase } from '../models';
import { useStyles } from './Page.styles';

const Page = ({
  columns,
  resourceType,
  form
}: Pick<
  ConfigurationBase,
  'columns' | 'form' | 'resourceType'
>): JSX.Element => {
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
        </PageHeader>
      </PageLayout.Header>
      <PageLayout.Body>
        <Box className={classes.pageBody}>
          <Listing columns={columns} />
        </Box>
      </PageLayout.Body>
      <Modal form={form} />
      <DeleteDialog />
      <DuplicateDialog />
    </PageLayout>
  );
};

export default Page;
