import { Divider, Typography } from '@mui/material';

import { PageLayout } from '@centreon/ui/components';

import type { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { DashboardAccessRightsModal } from './components/DashboardLibrary/DashboardAccessRights/DashboardAccessRightsModal';
import { DashboardConfigModal } from './components/DashboardLibrary/DashboardConfig/DashboardConfigModal';
import DeleteDashboardModal from './components/DashboardLibrary/DeleteDashboardModal';
import DuplicateDashboardModal from './components/DashboardLibrary/DuplicateDashboardModal';
import DashboardNavbar from './components/DashboardNavbar/DashboardNavbar';
import DashboardPageLayout from './components/DashboardPageLayout';
import { useDashboardsPageStyles } from './DashboardsPage.styles';
import {
  labelDashboards,
  labelDashboardsDescription
} from './translatedLabels';

const DashboardsPage = (): ReactElement => {
  const { t } = useTranslation();
  const { classes } = useDashboardsPageStyles();

  return (
    <PageLayout>
      <PageLayout.Header>
        <div className={classes.headerRow}>
          <div className={classes.titleGroup}>
            <Typography
              aria-label="page header title"
              className={classes.titleText}
              variant="h1"
            >
              {t(labelDashboards)}
            </Typography>
            <Divider
              className={classes.titleSeparator}
              flexItem
              orientation="vertical"
            />
            <Typography
              aria-label="page header description"
              className={classes.titleDescription}
              variant="body2"
            >
              {t(labelDashboardsDescription)}
            </Typography>
          </div>
          <DashboardNavbar />
        </div>
      </PageLayout.Header>
      <PageLayout.Body>
        <DashboardPageLayout />
      </PageLayout.Body>
      <DashboardConfigModal />
      <DashboardAccessRightsModal />
      <DeleteDashboardModal />
      <DuplicateDashboardModal />
    </PageLayout>
  );
};

export { DashboardsPage };
