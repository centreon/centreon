import { Box, TabsProps } from '@mui/material';
import { isNil } from 'ramda';
import { useMemo } from 'react';
import { Tabs } from '../../components/Tabs';
import { Group } from '../Inputs/models';
import { groupToTab } from './PanelTabs';

export interface FormSectionProps extends TabsProps {
    groups?: Group[];
}

export const useNavigateToSection = () => {
    return (sectionName) => {
        const section = document.querySelector(
            `[data-section-group-form-id="${sectionName}"]`
        );

        section?.scrollIntoView({ behavior: 'smooth' });
    };
};

const FormSection = ({ groups }: FormSectionProps) => {
    if (isNil(groups) || groups.length < 4) {
        return null;
    }

    const navigateToSection = useNavigateToSection();
    const tabMemo = useMemo(() => groupToTab(groups), [groups]);

    return (
        <Box className="sticky top-0 bg-background-paper z-100">
            <Tabs
                tabs={tabMemo}
                defaultTab={tabMemo[0].value}
                onChange={navigateToSection}
            />
        </Box>
    );
};

export { FormSection };
