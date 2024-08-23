import { Meta, StoryObj } from '@storybook/react';

import { Default as DefaultPageHeaderStory } from '../../Header/PageHeader/PageHeader.stories';
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

export const DefaultWithPageHeader: Story = {
  args: {
    children: (
      <>
        <PageLayout.Header>
          {/* eslint-disable react/jsx-pascal-case */}
          {/* @ts-expect-error storybook story render method not being recognised */}
          <DefaultPageHeaderStory.render {...DefaultPageHeaderStory.args} />
        </PageLayout.Header>
        <PageLayout.Body>
          <AreaIndicator height="100vh" name="body" />
        </PageLayout.Body>
      </>
    ),
    variant: 'default'
  }
};
