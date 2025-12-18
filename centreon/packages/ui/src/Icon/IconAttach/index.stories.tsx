import type { ComponentMeta, ComponentStory } from '@storybook/react';

import Icon from '.';

export default {
  argTypes: {
    defaultImage: { control: 'text' },
    imgSource: { control: 'text' },
    title: { control: 'text' },
    uploadedImage: { control: 'text' }
  },
  component: Icon,
  title: 'Icon/Attach'
} as ComponentMeta<typeof Icon>;

const TemplateIcon: ComponentStory<typeof Icon> = (args) => <Icon {...args} />;

export const PlaygroundIcon = TemplateIcon.bind({});

export const normal = (): JSX.Element => (
  <Icon
    defaultImage=""
    imgSource=""
    onClick={(): void => undefined}
    title="Attach"
    uploadedImage=""
  />
);
