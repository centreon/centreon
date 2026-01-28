import { type Theme, Typography } from '@mui/material';

import type { ComponentMeta, ComponentStory } from '@storybook/react';
import { makeStyles } from 'tss-react/mui';

import ContentWithCircularLoading from '.';

const useStyles = makeStyles()((theme: Theme) => ({
  container: {
    background: theme.palette.divider
  },
  root: {
    color: theme.palette.common.black
  }
}));

export default {
  argTypes: {
    alignCenter: { control: 'boolean' },
    children: { control: 'text' },
    loading: { control: 'boolean' },
    loadingIndicatorSize: { control: 'number' }
  },
  component: ContentWithCircularLoading,
  title: 'ContentWithCircularLoading'
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
  <ContentWithCircularLoading alignCenter={false} loading>
    <Content />
  </ContentWithCircularLoading>
);

export const loadingWithBiggerSize = (): JSX.Element => (
  <ContentWithCircularLoading loading loadingIndicatorSize={50}>
    <Content />
  </ContentWithCircularLoading>
);

const CustomLoading = (): JSX.Element => {
  const { classes } = useStyles();

  return (
    <ContentWithCircularLoading
      className={classes.root}
      loading
      loadingContainerClassname={classes.container}
      loadingIndicatorSize={50}
    >
      <Content />
    </ContentWithCircularLoading>
  );
};

export const customLoading = (): JSX.Element => <CustomLoading />;
