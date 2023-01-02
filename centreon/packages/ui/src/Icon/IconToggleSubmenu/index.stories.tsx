import { ComponentMeta, ComponentStory } from '@storybook/react';

import Icon from '.';

export default {
  component: Icon,
  title: 'Icon/ToggleSubmenu'
} as ComponentMeta<typeof Icon>;

interface HeaderProps {
  children: React.ReactNode;
}
const HeaderBackground = ({ children }: HeaderProps): JSX.Element => (
  <div style={{ backgroundColor: '#232f39', padding: '10px' }}>{children}</div>
);

const TemplateIcon: ComponentStory<typeof Icon> = (args) => (
  <HeaderBackground>
    <Icon {...args} />
  </HeaderBackground>
);

export const Arrow = TemplateIcon.bind({});
Arrow.args = {
  onClick: (): void => undefined,
  rotate: false
};

export const rotatedArrow = TemplateIcon.bind({});
rotatedArrow.args = {
  onClick: (): void => undefined,
  rotate: true
};
