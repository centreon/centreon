import { ComponentMeta, ComponentStory } from '@storybook/react';

import graphData from './data.json';

import Graph from './index';

export default {
  argTypes: {},
  component: Graph,
  title: 'Graph'
} as ComponentMeta<typeof Graph>;

const Template: ComponentStory<typeof Graph> = (args) => <Graph {...args} />;

export const Playground = Template.bind({});

Playground.args = {
  graphData
};
