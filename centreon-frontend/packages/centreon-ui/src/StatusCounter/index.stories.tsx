/* eslint-disable react/prop-types */
import { ComponentMeta, ComponentStory } from '@storybook/react';

import { SeverityCode } from '../StatusChip';

import StatusCounter from '.';

export default {
  argTypes: {
    count: { control: 'number' },
  },
  component: StatusCounter,
  title: 'StatusCounter',
} as ComponentMeta<typeof StatusCounter>;

const HeaderBackground = ({ children }): JSX.Element => (
  <div style={{ backgroundColor: '#232f39' }}>{children}</div>
);
const TemplateStatusCounter: ComponentStory<typeof StatusCounter> = (args) => (
  <HeaderBackground>
    <StatusCounter {...args} severityCode={SeverityCode.High} />
  </HeaderBackground>
);

export const PlaygroundStatusChip = TemplateStatusCounter.bind({});
PlaygroundStatusChip.args = {
  count: 4,
};

export const severityCodeHigh = (): JSX.Element => (
  <HeaderBackground>
    <StatusCounter count={3} severityCode={SeverityCode.High} />
  </HeaderBackground>
);

export const severityCodeMedium = (): JSX.Element => (
  <HeaderBackground>
    <StatusCounter count={3} severityCode={SeverityCode.Medium} />
  </HeaderBackground>
);

export const severityCodeLow = (): JSX.Element => (
  <HeaderBackground>
    <StatusCounter count={3} severityCode={SeverityCode.Low} />
  </HeaderBackground>
);

export const severityCodeOk = (): JSX.Element => (
  <HeaderBackground>
    <StatusCounter count={3} severityCode={SeverityCode.Ok} />
  </HeaderBackground>
);

export const severityCodeHighCount0 = (): JSX.Element => (
  <HeaderBackground>
    <StatusCounter count={0} severityCode={SeverityCode.High} />
  </HeaderBackground>
);

export const severityCodeMediumCount0 = (): JSX.Element => (
  <HeaderBackground>
    <StatusCounter count={0} severityCode={SeverityCode.Medium} />
  </HeaderBackground>
);

export const severityCodeLowCount0 = (): JSX.Element => (
  <HeaderBackground>
    <StatusCounter count={0} severityCode={SeverityCode.Low} />
  </HeaderBackground>
);

export const severityCodeOkCount0 = (): JSX.Element => (
  <HeaderBackground>
    <StatusCounter count={0} severityCode={SeverityCode.Ok} />
  </HeaderBackground>
);

export const severityCodeOkBigCount = (): JSX.Element => (
  <HeaderBackground>
    <StatusCounter count={500000} severityCode={SeverityCode.Ok} />
  </HeaderBackground>
);
