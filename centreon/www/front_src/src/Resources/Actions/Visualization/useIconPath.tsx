import { useTheme } from '@mui/material';

import { useAtomValue } from 'jotai';
import { equals } from 'ramda';

import { Visualization } from '../../models';
import { selectedVisualizationAtom } from '../actionsAtoms';

interface Props {
  IconOnActive: string;
  IconOnActiveDark: string;
  IconOnInactive: string;
  IconOnInactiveDark: string;
  type: Visualization;
}

const useIconPath = ({
  type,
  IconOnActive,
  IconOnActiveDark,
  IconOnInactive,
  IconOnInactiveDark
}: Props): string => {
  const theme = useTheme();
  const visualization = useAtomValue(selectedVisualizationAtom);

  const isDarkMode = equals(theme.palette.mode, 'dark');
  const isActive = equals(visualization, type);

  if (isActive) {
    return isDarkMode ? IconOnActiveDark : IconOnActive;
  }

  return isDarkMode ? IconOnInactiveDark : IconOnInactive;
};

export default useIconPath;
