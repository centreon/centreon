import { Box, type TabsProps } from '@mui/material';

import { isNil } from 'ramda';
import { useMemo } from 'react';

import { Tabs } from '../../components/Tabs';
import type { Group } from '../Inputs/models';
import { useNavigateToSection } from './navigateToSection';
import { groupToTab } from './PanelTabs';

export interface FormSectionProps extends TabsProps {
  groups?: Group[];
}

const FormSection = ({ groups }: FormSectionProps) => {
  const navigateToSection = useNavigateToSection();
  const tabMemo = useMemo(() => groupToTab(groups), [groups]);

  if (isNil(groups) || groups.length < 4) {
    return null;
  }

  return (
    <Box className="sticky top-0 bg-background-paper z-100">
      <Tabs
        variant="scrollable"
        scrollButtons={false}
        tabs={tabMemo}
        defaultTab={tabMemo[0].value}
        onChange={navigateToSection}
      />
    </Box>
  );
};

export { FormSection };
