import { ComponentMeta, ComponentStory } from '@storybook/react';

import { Typography } from '@mui/material';

import ContentWithCircularLoading from '.';

export default {
  argTypes: {
    alignCenter: { control: 'boolean' },
    children: { control: 'text' },
    loading: { control: 'boolean' },
    loadingIndicatorSize: { control: 'number' },
  },
  component: ContentWithCircularLoading,
  title: 'ContentWithCircularLoading',
} as ComponentMeta<typeof ContentWithCircularLoading>;

const TemplateContentWithCircularLoading: ComponentStory<
  typeof ContentWithCircularLoading
> = (args) => (
  <ContentWithCircularLoading {...args}>
    <Content />
  </ContentWithCircularLoading>
);

export const PlaygroundContentWithCircularLoading =
  TemplateContentWithCircularLoading.bind({});

const Content = (): JSX.Element => <Typography>Loaded</Typography>;

export const loading = (): JSX.Element => (
  <ContentWithCircularLoading loading>
    <Content />
  </ContentWithCircularLoading>
);

export const loaded = (): JSX.Element => (
  <ContentWithCircularLoading loading={false}>
    <Content />
  </ContentWithCircularLoading>
);

export const loadingNotCentered = (): JSX.Element => (
  <ContentWithCircularLoading loading alignCenter={false}>
    <Content />
  </ContentWithCircularLoading>
);

export const loadingWithBiggerSize = (): JSX.Element => (
  <ContentWithCircularLoading loading loadingIndicatorSize={50}>
    <Content />
  </ContentWithCircularLoading>
);
