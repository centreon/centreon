/* eslint-disable  @typescript-eslint/no-non-null-assertion */
import { ReactElement } from 'react';

import { Meta } from '@storybook/react';

import { Add as AddIcon, Share as ShareIcon } from '@mui/icons-material';

import { Button, PageHeader, PageLayout } from '../../components';
import { Menu } from '../../components/Menu';

const meta: Meta = {
  args: {
    actions: {}
  },
  parameters: {
    layout: 'fullscreen'
  },
  title: 'screens/Dashboards detail'
};

export default meta;

const DefaultView = (args): ReactElement => {
  const { data } = args;

  return (
    <PageLayout>
      <PageLayout.Header>
        <PageHeader>
          <PageHeader.Main>
            <PageHeader.Menu>
              <Menu>
                <Menu.Button />
                <Menu.Items>
                  <Menu.Item>Menu Item</Menu.Item>
                  <Menu.Item>Menu Item</Menu.Item>
                  <Menu.Item>Menu Item</Menu.Item>
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
              description={data.dashboard.description}
              title={data.dashboard.name}
            />
          </PageHeader.Main>
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
      </PageLayout.Body>
    </PageLayout>
  );
};

export const Default = {
  args: {
    data: {
      dashboard: {
        description:
          'Description et culpa sit commodo ea enim excepteur elit. Velit irure velit tempor culpa commodo eu adipisicing eu proident ullamco.',
        id: 1,
        name: 'Dashboard 1'
      }
    }
  },
  render: DefaultView
};

export const AsEditLayoutState = {
  args: {
    ...Default.args
  },
  render: DefaultView
};
