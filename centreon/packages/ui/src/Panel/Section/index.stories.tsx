import { ComponentMeta, ComponentStory } from '@storybook/react';

import { Typography } from '@mui/material';

import SectionPanel from '.';

export default {
  argsType: {
    loading: { control: 'bolean' },
    sections: { control: 'object' }
  },
  component: SectionPanel,
  title: 'Panel/Section'
} as ComponentMeta<typeof SectionPanel>;

interface Props {
  loading?;
  mainPanelWidth?: number;
  secondaryPanel?;
  sections;
}

const PanelWithHeader = ({
  sections,
  secondaryPanel = undefined,
  loading = false,
  mainPanelWidth = 550
}: Props): JSX.Element => (
  <div
    style={{ display: 'flex', flexDirection: 'row-reverse', height: '100vh' }}
  >
    <SectionPanel
      header={<Typography>Header</Typography>}
      loading={loading}
      mainPanelWidth={mainPanelWidth}
      secondaryPanel={secondaryPanel}
      sections={sections}
      onClose={(): undefined => undefined}
    />
  </div>
);

const sections = [
  {
    expandable: true,
    id: 'first section',
    section: <Typography>First section</Typography>,
    title: 'First section'
  },
  {
    expandable: true,
    id: 'second section',
    section: <Typography>Second section</Typography>,
    title: 'Second section'
  },
  {
    expandable: true,
    id: 'third section',
    section: <Typography>Third section</Typography>,
    title: 'Third section'
  }
];

const moreSections = [
  {
    expandable: true,
    id: 'fourth section',
    section: <Typography>Fourth section</Typography>,
    title: 'Fourth section'
  },
  {
    expandable: true,
    id: 'fifth section',
    section: <Typography>Fifth section</Typography>,
    title: 'Fifth section'
  },
  {
    expandable: true,
    id: 'sixth section',
    section: <Typography>Sixth section</Typography>,
    title: 'Sixth section'
  },
  {
    expandable: true,
    id: 'seventh section',
    section: <Typography>Seventh section</Typography>,
    title: 'Seventh section'
  },
  {
    expandable: true,
    id: 'eighth section',
    section: <Typography>Eighth section</Typography>,
    title: 'Eighth section'
  },
  {
    expandable: true,
    id: 'nineth section',
    section: <Typography>Nineth section</Typography>,
    title: 'Nineth section'
  }
];

const TemplateSectionPanel: ComponentStory<typeof SectionPanel> = (args) => (
  <PanelWithHeader {...args} />
);

export const PlaygroundSection = TemplateSectionPanel.bind({});
PlaygroundSection.args = {
  loading: false,
  sections
};

export const normal = (): JSX.Element => (
  <PanelWithHeader sections={sections} />
);

export const withMoreSections = (): JSX.Element => (
  <PanelWithHeader sections={sections.concat(moreSections)} />
);

export const withLoading = (): JSX.Element => (
  <PanelWithHeader loading sections={[]} />
);

export const withSecondaryPanel = (): JSX.Element => (
  <PanelWithHeader
    secondaryPanel={<Typography variant="h6">Secondary Panel</Typography>}
    sections={sections}
  />
);

export const withCustomSecondaryPanel = (): JSX.Element => (
  <PanelWithHeader
    mainPanelWidth={275}
    secondaryPanel={<Typography variant="h6">Secondary Panel</Typography>}
    sections={sections}
  />
);
