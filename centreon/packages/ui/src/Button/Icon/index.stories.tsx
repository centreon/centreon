import { ComponentStory, ComponentMeta } from '@storybook/react';
import { makeStyles } from 'tss-react/mui';

import AccessibilityIcon from '@mui/icons-material/Accessibility';
import { Theme } from '@mui/material';

import IconButton from '.';

const useStyles = makeStyles()((theme: Theme) => ({
  root: {
    '&:hover': {
      background: theme.palette.primary.dark
    },
    background: theme.palette.primary.light,
    color: theme.palette.common.white
  }
}));

export default {
  argTypes: {
    ariaLabel: { control: 'text' },
    title: { control: 'text' }
  },

  component: IconButton,
  title: 'Button/Icon'
} as ComponentMeta<typeof IconButton>;

const TemplateIconButton: ComponentStory<typeof IconButton> = (args) => (
  <IconButton {...args}>
    <AccessibilityIcon />
  </IconButton>
);

export const PlaygroundIconButton = TemplateIconButton.bind({});
PlaygroundIconButton.args = { ariaLabel: 'aria-label', title: 'Icon' };

const CustomIconButton = (): JSX.Element => {
  const { classes } = useStyles();

  return (
    <IconButton
      className={classes.root}
      title="custom Button"
      onClick={(): void => undefined}
    >
      <AccessibilityIcon />
    </IconButton>
  );
};

export const customIconButton = (): JSX.Element => <CustomIconButton />;
