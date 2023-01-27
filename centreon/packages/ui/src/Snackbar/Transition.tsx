import * as React from 'react';

import {
  TransitionProps as _TransitionProps,
  TransitionActions
} from 'react-transition-group/Transition';

import { Fade } from '@mui/material';

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
  style?: React.CSSProperties;
}

interface Props extends TransitionProps {
  children?: React.ReactNode;
}

const Transition = ({ children, ...rest }: Props): JSX.Element => (
  <Fade {...rest}>
    <div>{children}</div>
  </Fade>
);

export default Transition;
