import { Box } from '@mui/material';

import { PageHeader, PageLayout } from '@centreon/ui/components';
import { useTranslation } from 'react-i18next';
import { DeleteDialog, DisableDialog, EnableDialog } from './Dialogs';

import { Listing } from './Listing';
import Modal from './Modal';
import { useStyles } from './Page.styles';
import { labelAuthenticationTokens } from './translatedLabels';

const Page = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  return (
    <PageLayout>
      <PageLayout.Header>
        <PageHeader>
          <PageHeader.Main>
            <PageHeader.Title title={t(labelAuthenticationTokens)} />
          </PageHeader.Main>
        </PageHeader>
      </PageLayout.Header>
      <PageLayout.Body>
        <Box className={classes.listing}>
          <Listing />
        </Box>
      </PageLayout.Body>
      <Modal />
      <DeleteDialog />
      <DisableDialog />
      <EnableDialog />
    </PageLayout>
  );
};

export default Page;
