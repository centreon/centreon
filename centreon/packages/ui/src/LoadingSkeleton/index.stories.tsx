import { ComponentMeta, ComponentStory } from '@storybook/react';
import { makeStyles } from 'tss-react/mui';

import { Theme } from '@mui/material';

import LoadingSkeleton from '.';

const useStyles = makeStyles()((theme: Theme) => ({
  root: {
    background: theme.palette.divider,
    borderRadius: theme.spacing(0.5),
    height: theme.spacing(5),
    width: theme.spacing(30)
  }
}));

export default {
  argTypes: {
    height: { control: 'number' },
    width: { control: 'number' }
  },
  component: LoadingSkeleton,
  title: 'Loading Skeleton'
} as ComponentMeta<typeof LoadingSkeleton>;

const TemplateLoadingSkeleton: ComponentStory<typeof LoadingSkeleton> = (
  args
) => <LoadingSkeleton {...args} />;

export const PlaygroundLoadingSkeleton = TemplateLoadingSkeleton.bind({});
PlaygroundLoadingSkeleton.args = { height: 50, width: 400 };

export const normal = (): JSX.Element => (
  <LoadingSkeleton height={50} width={400} />
);

const Custom = (): JSX.Element => {
  const { classes } = useStyles();

  return <LoadingSkeleton className={classes.root} />;
};

export const custom = (): JSX.Element => <Custom />;
