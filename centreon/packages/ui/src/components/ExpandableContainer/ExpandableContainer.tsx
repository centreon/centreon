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
  const { classes } = useStyles();

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
        <Modal
          classes={{
            paper: classes.papper
          }}
          hasCloseButton={false}
          open={isExpanded}
          PaperProps={{
            style: {
              maxWidth: '90vw',
              width: '90vw'
            }
          }}
          size="xlarge"
        >
          {children(expandedChildrenData)}
        </Modal>
      )}
    </>
  );
};

export default ExpandableContainer;
