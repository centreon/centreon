import { usePluralizedTranslation } from '@centreon/ui';
import { Box, capitalize } from '@mui/material';
import { JSX } from 'react';
import { DeleteDialog, DuplicateDialog } from './Dialogs';

import { PageHeader, PageLayout } from '@centreon/ui/components';
import { Listing } from './Listing';
import { Modal } from './Modal';

import { ConfigurationBase } from '../models';
import { useStyles } from './Page.styles';

const Page = ({
  columns,
  resourceType,
  form,
  hasWriteAccess,
  actions
}: Pick<
  ConfigurationBase,
  'columns' | 'form' | 'resourceType' | 'hasWriteAccess' | 'actions'
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
          <Listing
            columns={columns}
            hasWriteAccess={hasWriteAccess}
            actions={actions}
          />
        </Box>
      </PageLayout.Body>
      <Modal form={form} hasWriteAccess={hasWriteAccess} />
      <DeleteDialog />
      <DuplicateDialog />
    </PageLayout>
  );
};

export default Page;
