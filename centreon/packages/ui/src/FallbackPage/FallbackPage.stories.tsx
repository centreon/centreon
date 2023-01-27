import { ComponentMeta, ComponentStory } from '@storybook/react';
import { makeStyles } from 'tss-react/mui';

import { FallbackPage } from './FallbackPage';

const useStyles = makeStyles()({
  container: {
    height: '100vh'
  }
});

export default {
  argTypes: {
    contactAdmin: { control: 'text' },
    message: { control: 'text' },
    title: { control: 'text' }
  },
  component: FallbackPage,
  title: 'FallbackPage/LicenceInvalid'
} as ComponentMeta<typeof FallbackPage>;

const TemplateBackground: ComponentStory<typeof FallbackPage> = (args) => {
  const { classes } = useStyles();

  return (
    <div className={classes.container}>
      <FallbackPage {...args} />
    </div>
  );
};

export const LicenceInvalid = TemplateBackground.bind({});
LicenceInvalid.args = {
  contactAdmin: 'Contact your administrator',
  message: 'Invalid licence',
  title: 'Oops'
};
