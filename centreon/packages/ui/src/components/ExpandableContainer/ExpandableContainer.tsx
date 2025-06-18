import { OpenInFull } from '@mui/icons-material';
import { useState } from 'react';
import { Modal } from '../Modal';
import { useStyles } from './expandableContainer.styles';
import { Parameters } from './models';
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
    toggleExpand,
    isExpanded: false,
    label: labelExpand,
    Icon: OpenInFull,
    key: currentMode
  };

  const expandedChildrenData = {
    toggleExpand,
    isExpanded,
    label: labelReduce,
    Icon: OpenInFull,
    key: currentMode
  };

  return (
    <>
      {children(reducedChildrenData)}
      {isExpanded && (
        <Modal
          open={isExpanded}
          size="xlarge"
          classes={{
            paper: classes.papper
          }}
          PaperProps={{
            style: {
              width: '90vw',
              maxWidth: '90vw'
            }
          }}
          hasCloseButton={false}
        >
          {children(expandedChildrenData)}
        </Modal>
      )}
    </>
  );
};

export default ExpandableContainer;
