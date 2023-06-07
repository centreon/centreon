import { ReactElement } from 'react';

import { Add as AddIcon, Share as ShareIcon } from '@mui/icons-material';

import { Button, Menu, PageHeader, PageLayout } from '@centreon/ui/components';

import Layout from './Layout';
import useDashboardDetails, { routerParams } from './useDashboardDetails';
import HeaderActions from './HeaderActions';

const Dashboard = (): ReactElement => {
  const { dashboardId } = routerParams.useParams();
  const { dashboard, panels } = useDashboardDetails({ dashboardId });

  return (
    <PageLayout>
      <PageLayout.Header>
        <PageHeader>
          <PageHeader.Main>
            <PageHeader.Menu>
              <Menu>
                <Menu.Button />
                <Menu.Items>
                  <Menu.Item isActive isDisabled>
                    {dashboard?.name || ''}
                  </Menu.Item>
                  <Menu.Divider />
                  <Menu.Item>
                    <Button
                      icon={<AddIcon />}
                      iconVariant="start"
                      variant="ghost"
                    >
                      Add item
                    </Button>
                  </Menu.Item>
                </Menu.Items>
              </Menu>
            </PageHeader.Menu>
            <PageHeader.Title
              description={dashboard?.description || ''}
              title={dashboard?.name || ''}
            />
          </PageHeader.Main>
          <PageHeader.Actions>
            <HeaderActions
              id={dashboard?.id}
              name={dashboard?.name}
              panels={panels}
            />
          </PageHeader.Actions>
        </PageHeader>
      </PageLayout.Header>
      <PageLayout.Body>
        <PageLayout.Actions>
          <Button
            icon={<ShareIcon />}
            iconVariant="start"
            size="small"
            variant="ghost"
          >
            Share
          </Button>
        </PageLayout.Actions>

        <Layout />
      </PageLayout.Body>
    </PageLayout>
  );
};

export default Dashboard;
