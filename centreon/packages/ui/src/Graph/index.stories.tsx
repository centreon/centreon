import { useState } from 'react';

import { ComponentMeta, ComponentStory } from '@storybook/react';

import TimePeriod from '../TimePeriods';

import graphData from './data.json';

import Graph from './index';

export default {
  argTypes: {},
  component: Graph,
  title: 'Graph'
} as ComponentMeta<typeof Graph>;

const Template: ComponentStory<typeof Graph> = (args) => {
  const [endpoint, setEndpoint] = useState<string | null>(null);
  const graphEndpoint =
    'http://localhost:3000/centreon/api/latest/monitoring/hosts/151/services/1160/metrics/performance';

  const getGraphParameters = (data: string): void => {
    setEndpoint(`${graphEndpoint}${data}`);
  };

  return (
    <>
      <TimePeriod getGraphParameters={getGraphParameters} />
      <Graph {...args} graphEndpoint={endpoint} />
    </>
  );
};

export const Playground = Template.bind({});

Playground.args = {
  graphData
};
