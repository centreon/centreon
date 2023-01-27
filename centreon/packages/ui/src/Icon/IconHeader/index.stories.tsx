/* eslint-disable no-alert */
/* eslint-disable react/prop-types */

import { ComponentMeta, ComponentStory } from '@storybook/react';

import DnsIcon from '@mui/icons-material/Dns';

import IconHeader from '.';

export default {
  argTypes: {
    iconName: { control: 'text' }
  },
  component: IconHeader,
  title: 'Icon/Header'
} as ComponentMeta<typeof IconHeader>;

const alertOnClick = (name): void => {
  alert(`${name} clicked`);
};

const HeaderBackground = ({ children, color = undefined }): JSX.Element => (
  <div style={{ backgroundColor: color || '#232f39' }}>{children}</div>
);

const TemplateIcon: ComponentStory<typeof IconHeader> = (args) => (
  <HeaderBackground>
    <IconHeader {...args} Icon={DnsIcon} />
  </HeaderBackground>
);

export const PlaygroundIcon = TemplateIcon.bind({});
PlaygroundIcon.args = {
  iconName: 'Poller'
};

export const normal = (): JSX.Element => (
  <HeaderBackground>
    <IconHeader
      Icon={DnsIcon}
      iconName="hosts"
      onClick={(): void => alertOnClick('Home')}
    />
  </HeaderBackground>
);

export const withPending = (): JSX.Element => (
  <HeaderBackground>
    <IconHeader
      pending
      Icon={DnsIcon}
      iconName="hosts"
      onClick={(): void => alertOnClick('Home')}
    />
  </HeaderBackground>
);
