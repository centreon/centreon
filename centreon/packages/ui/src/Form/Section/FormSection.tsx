import { Box, type TabsProps } from '@mui/material';

import { isNil } from 'ramda';
import { type RefObject, useMemo } from 'react';

import { Tabs } from '../../components/Tabs';
import type { Group } from '../Inputs/models';
import { groupToTab } from './PanelTabs';
import { useActiveSection } from './useActiveSection';

export interface FormSectionProps extends TabsProps {
  containerRef: RefObject<HTMLElement | null>;
  groups?: Array<Group>;
}

const FormSection = ({ groups, containerRef }: FormSectionProps) => {
  const tabMemo = useMemo(() => groupToTab(groups), [groups]);

  const hasTabs = !isNil(groups) && groups.length >= 4;

  const sectionNames = useMemo(
    () => tabMemo.map(({ value }) => value),
    [tabMemo]
  );

  const { activeSection, goToSection } = useActiveSection({
    containerRef,
    isEnabled: hasTabs,
    sectionNames
  });

  if (!hasTabs) {
    return null;
  }

  return (
    <Box className="sticky top-0 bg-background-paper z-100">
      <Tabs
        defaultTab={tabMemo[0].value}
        onChange={goToSection}
        scrollButtons={false}
        selectedTab={activeSection}
        tabs={tabMemo}
        variant="scrollable"
      />
    </Box>
  );
};

export { FormSection };
