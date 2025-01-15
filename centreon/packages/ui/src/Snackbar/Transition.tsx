import {
  TransitionActions,
  TransitionProps as _TransitionProps
} from 'react-transition-group/Transition';

import { Fade } from '@mui/material';
import { CSSProperties, ReactNode } from 'react';

type TransitionHandlerKeys =
  | 'onEnter'
  | 'onEntering'
  | 'onEntered'
  | 'onExit'
  | 'onExiting'
  | 'onExited';
type TransitionKeys =
  | 'in'
  | 'mountOnEnter'
  | 'unmountOnExit'
  | 'timeout'
  | 'addEndListener'
  | TransitionHandlerKeys;
interface TransitionProps
  extends TransitionActions,
    Partial<Pick<_TransitionProps, TransitionKeys>> {
  style?: CSSProperties;
}

interface Props extends TransitionProps {
  children?: ReactNode;
}

const Transition = ({ children, ...rest }: Props): JSX.Element => (
  <Fade {...rest}>
    <div>{children}</div>
  </Fade>
);

export default Transition;
