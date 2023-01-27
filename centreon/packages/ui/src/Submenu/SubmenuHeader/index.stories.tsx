import { ComponentMeta, ComponentStory } from '@storybook/react';
import { BrowserRouter } from 'react-router-dom';

import HostIcon from '@mui/icons-material/Dns';
import ServiceIcon from '@mui/icons-material/Grain';

import { SeverityCode } from '../../StatusChip';

import SubmenuHeader, { Props } from '.';

export default {
  component: SubmenuHeader,
  title: 'SubmenuHeader'
} as ComponentMeta<typeof SubmenuHeader>;

interface HeaderProps {
  children: React.ReactNode;
}

const HeaderBackground = ({ children }: HeaderProps): JSX.Element => (
  <div style={{ backgroundColor: '#232f39' }}>{children}</div>
);

const Template: ComponentStory<typeof SubmenuHeader> = (args: Props) => (
  <BrowserRouter>
    <HeaderBackground>
      <SubmenuHeader {...args} />
    </HeaderBackground>
  </BrowserRouter>
);

export const HostMenu = Template.bind({});
HostMenu.args = {
  active: true,
  counters: [
    {
      count: 5,
      onClick: (): void => undefined,
      severityCode: SeverityCode.High,
      to: '#'
    },
    {
      count: 45,
      onClick: (): void => undefined,
      severityCode: SeverityCode.Low,
      to: '#'
    },
    {
      count: 51,
      onClick: (): void => undefined,
      severityCode: SeverityCode.Ok,
      to: '#'
    }
  ],
  hasPending: false,
  iconHeader: {
    Icon: HostIcon,
    iconName: 'Host',
    onClick: (): void => undefined
  },
  iconToggleSubmenu: {
    onClick: (): void => undefined,
    rotate: false
  },
  submenuItems: [
    {
      onClick: (): void => undefined,
      submenuCount: 64,
      submenuTitle: 'All',
      to: '#'
    },
    {
      onClick: (): void => undefined,
      severityCode: SeverityCode.High,
      submenuCount: 5,
      submenuTitle: 'Critical',
      to: '#'
    },
    {
      onClick: (): void => undefined,
      severityCode: SeverityCode.Low,
      submenuCount: 45,
      submenuTitle: 'Unknown',
      to: '#'
    },
    {
      onClick: (): void => undefined,
      severityCode: SeverityCode.Ok,
      submenuCount: 12,
      submenuTitle: 'Ok',
      to: '#'
    },
    {
      onClick: (): void => undefined,
      severityCode: SeverityCode.Pending,
      submenuCount: 2,
      submenuTitle: 'Pending',
      to: '#'
    }
  ],
  toggled: false
};

export const ServiceMenu = Template.bind({});
ServiceMenu.args = {
  active: true,
  counters: [
    {
      count: SeverityCode.High,
      onClick: (): void => undefined,
      severityCode: 1,
      to: '#'
    },
    {
      count: SeverityCode.Medium,
      onClick: (): void => undefined,
      severityCode: 2,
      to: '#'
    },
    {
      count: SeverityCode.Low,
      onClick: (): void => undefined,
      severityCode: 5,
      to: '#'
    },
    {
      count: SeverityCode.Ok,
      onClick: (): void => undefined,
      severityCode: 4,
      to: '#'
    }
  ],
  hasPending: false,
  iconHeader: {
    Icon: ServiceIcon,
    iconName: 'Service',
    onClick: (): void => undefined
  },
  iconToggleSubmenu: {
    onClick: (): void => undefined,
    rotate: false
  },
  submenuItems: [
    {
      onClick: (): void => undefined,
      submenuCount: 14,
      submenuTitle: 'All',
      to: '#'
    },
    {
      onClick: (): void => undefined,
      severityCode: SeverityCode.High,
      submenuCount: 1,
      submenuTitle: 'Critical',
      to: '#'
    },
    {
      onClick: (): void => undefined,
      severityCode: SeverityCode.Medium,
      submenuCount: 2,
      submenuTitle: 'Warning',
      to: '#'
    },
    {
      onClick: (): void => undefined,
      severityCode: SeverityCode.Low,
      submenuCount: 5,
      submenuTitle: 'Unknown',
      to: '#'
    },
    {
      onClick: (): void => undefined,
      severityCode: SeverityCode.Ok,
      submenuCount: 4,
      submenuTitle: 'Ok',
      to: '#'
    },
    {
      onClick: (): void => undefined,
      severityCode: SeverityCode.Pending,
      submenuCount: 2,
      submenuTitle: 'Pending',
      to: '#'
    }
  ],
  toggled: false
};
