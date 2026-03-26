import { Box, type TabsProps } from '@mui/material';

import { isNil } from 'ramda';
import { useMemo } from 'react';

import { Tabs } from '../../components/Tabs';
import type { Group } from '../Inputs/models';
import { useNavigateToSection } from './navigateToSection';
import { groupToTab } from './PanelTabs';

export interface FormSectionProps extends TabsProps {
  groups?: Array<Group>;
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
        defaultTab={tabMemo[0].value}
        onChange={navigateToSection}
        scrollButtons={false}
        tabs={tabMemo}
        variant="scrollable"
      />
    </Box>
  );
};

export { FormSection };
