import { CloseFullscreen, OpenInFull } from '@mui/icons-material';
import {
  CSSProperties,
  forwardRef,
  useCallback,
  useEffect,
  useMemo,
  useState
} from 'react';
import { Modal } from '../Modal';
import { useStyles } from './expandableContainer.styles';
import { Parameters } from './models';

interface Props {
  children: (params: Parameters) => JSX.Element;
  style?: CSSProperties;
  getCurrentElement: (element: HTMLDivElement) => void;
}

const ExpandableContainer = forwardRef<HTMLDivElement, Props>(
  ({ children, style, getCurrentElement }, ref) => {
    const { classes } = useStyles();

    const [isExpanded, setIsExpanded] = useState(false);

    const toggleExpand = useCallback(() => {
      setIsExpanded(!isExpanded);
    }, []);

    const label = isExpanded ? 'Reduce' : 'Extend';

    const commonData = useMemo(
      () => ({
        toggleExpand,
        isExpanded,
        label,
        Icon: isExpanded ? CloseFullscreen : OpenInFull
      }),
      [toggleExpand, isExpanded, label]
    );

    useEffect(() => {
      getCurrentElement(ref?.current);
    }, [isExpanded]);

    return (
      <>
        {children({ ...commonData, style, ref })}
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
            {children({
              ...commonData,
              style: { height: '100%', width: '100%' },
              ref
            })}
          </Modal>
        )}
      </>
    );
  }
);

export default ExpandableContainer;
