import { usePluralizedTranslation } from '@centreon/ui';
import { Box, capitalize } from '@mui/material';
import { JSX } from 'react';
import { DeleteDialog, DuplicateDialog } from './Dialogs';

import { PageHeader, PageLayout } from '@centreon/ui/components';
import { Listing } from './Listing';
import { Modal } from './Modal';

import { or } from 'ramda';
import { ConfigurationBase } from '../models';
import { useStyles } from './Page.styles';

const Page = ({
  columns,
  resourceType,
  form,
  actions
}: Pick<
  ConfigurationBase,
  'columns' | 'form' | 'resourceType' | 'actions'
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
            hasWriteAccess={!!actions?.edit}
            actions={actions}
          />
        </Box>
      </PageLayout.Body>
      {or(!!actions?.edit, !!actions?.viewDetails) && (
        <Modal form={form} hasWriteAccess={!!actions?.edit} />
      )}
      {actions?.delete && <DeleteDialog />}
      {actions?.duplicate && <DuplicateDialog />}
    </PageLayout>
  );
};

export default Page;
