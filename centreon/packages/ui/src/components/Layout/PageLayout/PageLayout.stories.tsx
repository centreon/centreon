import { Meta, StoryObj } from '@storybook/react';

import { AreaIndicator } from '../AreaIndicator';

import { PageLayout } from './index';

const meta: Meta<typeof PageLayout> = {
  argTypes: {
    variant: {
      control: {
        options: ['default', 'fixed-header']
      }
    }
  },
  component: PageLayout,
  parameters: {
    layout: 'fullscreen'
  }
};

export default meta;
type Story = StoryObj<typeof PageLayout>;

export const Default: Story = {
  args: {
    children: (
      <>
        <PageLayout.Header>
          <AreaIndicator name="header" />
        </PageLayout.Header>
        <PageLayout.Body>
          <AreaIndicator height="100vh" name="body">
            <PageLayout.Actions>
              <AreaIndicator depth={1} name="actions" />
            </PageLayout.Actions>
          </AreaIndicator>
        </PageLayout.Body>
      </>
    ),
    variant: 'default'
  }
};

export const FixedHeader: Story = {
  args: {
    children: (
      <>
        <PageLayout.Header>
          <AreaIndicator name="header" />
        </PageLayout.Header>
        <PageLayout.Body hasBackground>
          <AreaIndicator height="100vh" name="body">
            <PageLayout.Actions>
              <AreaIndicator depth={1} name="actions" />
            </PageLayout.Actions>
          </AreaIndicator>
        </PageLayout.Body>
      </>
    ),
    variant: 'fixed-header'
  }
};
