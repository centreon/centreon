import { OpenInFull } from '@mui/icons-material';
import { CSSProperties, useState } from 'react';
import { Modal } from '../Modal';
import { useStyles } from './expandableContainer.styles';
import { Parameters } from './models';
import { labelExpand, labelReduce } from './translatedLabels';

interface Props {
  children: (params: Parameters) => JSX.Element;
  style?: CSSProperties;
}

const ExpandableContainer = ({ children, style }: Props) => {
  const { classes } = useStyles();

  const [isExpanded, setIsExpanded] = useState(false);

  const toggleExpand = (): void => {
    setIsExpanded(!isExpanded);
  };

  const reducedChildrenData = {
    toggleExpand,
    isExpanded: false,
    label: labelExpand,
    Icon: OpenInFull,
    key: labelExpand
  };

  const expandedChildrenData = {
    toggleExpand,
    isExpanded,
    label: labelReduce,
    Icon: OpenInFull,
    key: labelReduce
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
