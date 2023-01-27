import { ComponentMeta, ComponentStory } from '@storybook/react';
import { makeStyles } from 'tss-react/mui';

import { Theme } from '@mui/material';

import ButtonSave from '.';

const useStyles = makeStyles()((theme: Theme) => ({
  root: {
    background: theme.palette.error.main,
    borderRadius: theme.spacing(0.5),
    width: theme.spacing(10)
  }
}));

export default {
  argTypes: {
    labelLoading: { control: 'text' },
    labelSave: { control: 'text' },
    labelSucceeded: { control: 'text' },
    loading: { control: 'boolean' },
    size: { control: 'select', options: ['small', 'medium', 'large'] },
    succeeded: { control: 'boolean' },
    tooltipLabel: { control: 'text' }
  },

  component: ButtonSave,

  title: 'Button/Save'
} as ComponentMeta<typeof ButtonSave>;

const TemplateButtonSave: ComponentStory<typeof ButtonSave> = (args) => (
  <ButtonSave {...args} />
);

export const PlaygroundButton = TemplateButtonSave.bind({});

export const normal = (): JSX.Element => <ButtonSave />;

export const loading = (): JSX.Element => <ButtonSave loading />;

export const succeeded = (): JSX.Element => <ButtonSave succeeded />;

export const normalWithText = (): JSX.Element => (
  <ButtonSave labelSave="Save" />
);

export const largeWithText = (): JSX.Element => (
  <ButtonSave labelSave="Save" size="large" />
);

export const loadingWithTextAndMediumSize = (): JSX.Element => (
  <ButtonSave loading labelLoading="Loading" />
);

export const succeededWithText = (): JSX.Element => (
  <ButtonSave succeeded labelSucceeded="Succeeded" />
);

export const normalWithTextAndSmallSize = (): JSX.Element => (
  <ButtonSave labelSave="Save" size="small" />
);

export const loadingWithTextAndSmallSize = (): JSX.Element => (
  <ButtonSave loading labelLoading="Loading" size="small" />
);

export const loadingWithTextAndLargeSize = (): JSX.Element => (
  <ButtonSave loading labelLoading="Loading" size="large" />
);

export const succeededWithTextAndSmallSize = (): JSX.Element => (
  <ButtonSave succeeded labelSucceeded="Succeeded" size="small" />
);

const CustomButton = (): JSX.Element => {
  const { classes } = useStyles();

  return <ButtonSave className={classes.root} />;
};

export const customButton = (): JSX.Element => <CustomButton />;
