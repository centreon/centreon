import { useAtomValue } from 'jotai';
import { equals } from 'ramda';

import { Visualization } from '../../models';
import { selectedVisualizationAtom } from '../actionsAtoms';

interface Props {
  IconOnActive: string;
  IconOnInactive: string;
  type: Visualization;
}

const useIconPath = ({ type, IconOnActive, IconOnInactive }: Props): string => {
  const visualization = useAtomValue(selectedVisualizationAtom);

  const imagePath = equals(visualization, type) ? IconOnActive : IconOnInactive;

  return imagePath;
};

export default useIconPath;
