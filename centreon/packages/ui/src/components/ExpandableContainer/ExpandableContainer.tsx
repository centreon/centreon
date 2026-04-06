import { OpenInFull } from '@mui/icons-material';

import { useState } from 'react';

import { Modal } from '../Modal';
import { useStyles } from './expandableContainer.styles';
import type { Parameters } from './models';
import { labelExpand, labelReduce } from './translatedLabels';

interface Props {
  children: (params: Omit<Parameters, 'ref'>) => JSX.Element;
}

const ExpandableContainer = ({ children }: Props) => {
  const { classes: _classes } = useStyles();

  const [isExpanded, setIsExpanded] = useState(false);

  const toggleExpand = (): void => {
    setIsExpanded(!isExpanded);
  };
  const currentMode = isExpanded ? labelExpand : labelReduce;

  const reducedChildrenData = {
    Icon: OpenInFull,
    isExpanded: false,
    key: currentMode,
    label: labelExpand,
    toggleExpand
  };

  const expandedChildrenData = {
    Icon: OpenInFull,
    isExpanded,
    key: currentMode,
    label: labelReduce,
    toggleExpand
  };

  return (
    <>
      {children(reducedChildrenData)}
      {isExpanded && (
        <Modal hasCloseButton={false} open={isExpanded} size="xlarge">
          {children(expandedChildrenData)}
        </Modal>
      )}
    </>
  );
};

export default ExpandableContainer;
