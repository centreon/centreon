import { ComponentMeta, ComponentStory } from '@storybook/react';

import { Typography } from '@mui/material';

import Panel from '.';

export default {
  component: Panel,
  title: 'Panel'
} as ComponentMeta<typeof Panel>;

const header = <Typography>Header</Typography>;
const tab = <Typography>Tab</Typography>;

const TemplatePanel: ComponentStory<typeof Panel> = (args) => (
  <Story {...args} />
);

export const DynamicPanel = TemplatePanel.bind({});

const Story = (props): JSX.Element => {
  return (
    <div
      style={{ display: 'flex', flexDirection: 'row-reverse', height: '100vh' }}
    >
      <Panel header={header} selectedTab={tab} {...props} />
    </div>
  );
};

export const normal = (): JSX.Element => <Story />;
