/* eslint-disable react/prop-types */
import { ComponentMeta, ComponentStory } from '@storybook/react';

import { SeverityCode } from '../StatusChip';

import StatusCounter, { Props } from '.';

export default {
  argTypes: {
    count: { control: 'number' },
  },
  component: StatusCounter,
  title: 'StatusCounter',
} as ComponentMeta<typeof StatusCounter>;

interface HeaderProps {
  children: React.ReactNode;
}

const HeaderBackground = ({ children }: HeaderProps): JSX.Element => (
  <div style={{ backgroundColor: '#232f39' }}>{children}</div>
);
const TemplateStatusCounter: ComponentStory<typeof StatusCounter> = (
  args: Props,
) => (
  <HeaderBackground>
    <StatusCounter {...args} />
  </HeaderBackground>
);

const PlaygroundStatusChip = TemplateStatusCounter.bind({});
PlaygroundStatusChip.args = {
  count: 4,
};

export const SeverityCodeHigh = TemplateStatusCounter.bind({});
SeverityCodeHigh.args = {
  count: 3,
  severityCode: SeverityCode.High,
};

export const SeverityCodeMedium = TemplateStatusCounter.bind({});
SeverityCodeMedium.args = {
  count: 3,
  severityCode: SeverityCode.Medium,
};

export const SeverityCodeLow = TemplateStatusCounter.bind({});
SeverityCodeLow.args = {
  count: 3,
  severityCode: SeverityCode.Low,
};

export const SeverityCodeOk = TemplateStatusCounter.bind({});
SeverityCodeOk.args = {
  count: 3,
  severityCode: SeverityCode.Ok,
};

export const SeverityCodeOkBigCount = TemplateStatusCounter.bind({});
SeverityCodeOkBigCount.args = {
  count: 500000,
  severityCode: SeverityCode.Ok,
};
