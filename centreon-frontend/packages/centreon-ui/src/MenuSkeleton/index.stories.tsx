import { ComponentMeta, ComponentStory } from '@storybook/react';
import { makeStyles } from 'tss-react/mui';

import MenuLoader from '.';

export default {
  argTypes: {
    animate: { control: 'boolean' },
    width: { control: 'number' },
  },
  component: MenuLoader,
  title: 'Menu Skeleton',
} as ComponentMeta<typeof MenuLoader>;

const useStyles = makeStyles()((theme) => ({
  container: {
    backgroundColor: theme.palette.primary.main,
  },
}));

interface Props {
  width?: number;
}

const MenuLoaderStory = ({ width }: Props): JSX.Element => {
  const { classes } = useStyles();

  return (
    <div className={classes.container}>
      <MenuLoader animate={false} width={width} />
    </div>
  );
};

const TemplateMenuLoaderStory: ComponentStory<typeof MenuLoader> = (args) => (
  <MenuLoaderStory {...args} />
);

export const PlaygroundMenuLoaderStory = TemplateMenuLoaderStory.bind({});
PlaygroundMenuLoaderStory.args = {
  animate: false,
  width: 40,
};

export const menuLoader = (): JSX.Element => <MenuLoaderStory />;

export const menuLoaderWithCustomWidth = (): JSX.Element => (
  <MenuLoaderStory width={40} />
);
