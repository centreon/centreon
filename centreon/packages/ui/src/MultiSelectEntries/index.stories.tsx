import type { ComponentMeta, ComponentStory } from '@storybook/react';

import MultiSelectEntries from '.';
import '../ThemeProvider/tailwindcss.css';

export default {
  argTypes: {
    emptyLabel: { control: 'text' },
    highlight: { control: 'boolean' },
    label: { control: 'text' },
    values: { control: 'object' }
  },
  component: MultiSelectEntries,
  title: 'MultiSelectEntries'
} as ComponentMeta<typeof MultiSelectEntries>;

const label = 'Entries';
const emptyLabel = 'Click to add Entries';

const sixElement = new Array(6).fill(0);

const entries = [...sixElement].map((_, index) => ({
  id: index,
  name: `Entry ${index}`
}));

const noOp = (): void => undefined;

const TemplateMultiSelectEntries: ComponentStory<typeof MultiSelectEntries> = (
  args
) => <MultiSelectEntries {...args} />;

export const PlaygroundMultiSelectEntries = TemplateMultiSelectEntries.bind({});
PlaygroundMultiSelectEntries.args = {
  emptyLabel,
  highlight: false,
  label,
  values: entries
};

export const empty = (): JSX.Element => (
  <MultiSelectEntries emptyLabel={emptyLabel} label={label} onClick={noOp} />
);

export const oneElement = (): JSX.Element => (
  <MultiSelectEntries
    emptyLabel={emptyLabel}
    label={label}
    onClick={noOp}
    values={[entries[0]]}
  />
);

export const oneElementHighlight = (): JSX.Element => (
  <MultiSelectEntries
    emptyLabel={emptyLabel}
    highlight
    label={label}
    onClick={noOp}
    values={[entries[0]]}
  />
);

export const sixElements = (): JSX.Element => (
  <MultiSelectEntries
    emptyLabel={emptyLabel}
    label={label}
    onClick={noOp}
    values={entries}
  />
);

export const sixElementsError = (): JSX.Element => (
  <MultiSelectEntries
    emptyLabel={emptyLabel}
    error="Something went wrong"
    label={label}
    onClick={noOp}
    values={entries}
  />
);
