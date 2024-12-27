import { OpenInFull } from '@mui/icons-material';
import { CSSProperties, forwardRef, useEffect, useState } from 'react';
import { Modal } from '../Modal';
import { useStyles } from './expandableContainer.styles';
import { Parameters } from './models';
import { labelExpand, labelReduce } from './translatedLabels';

interface Props {
  children: (params: Parameters) => JSX.Element;
  style?: CSSProperties;
  getCurrentElement: (element: HTMLDivElement) => void;
}

const ExpandableContainer = forwardRef<HTMLDivElement, Props>(
  ({ children, style, getCurrentElement }, ref) => {
    const { classes } = useStyles();

    const [isExpanded, setIsExpanded] = useState(false);

    const toggleExpand = () => {
      setIsExpanded(!isExpanded);
    };

    const keyCurrentCase = isExpanded ? labelExpand : labelReduce;

    const reducedChildrenData = {
      toggleExpand,
      isExpanded: false,
      label: labelExpand,
      Icon: OpenInFull,
      ref,
      style,
      key: keyCurrentCase
    };

    const expandedChildrenData = {
      toggleExpand,
      isExpanded,
      label: labelReduce,
      Icon: OpenInFull,
      ref,
      style: { height: '100%', width: '100%' },
      key: keyCurrentCase
    };

    useEffect(() => {
      getCurrentElement(ref?.current);
    }, [isExpanded]);

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
  }
);

export default ExpandableContainer;
