import * as React from 'react';

import { always, cond, not, pipe, propEq, T } from 'ramda';

import { CircularProgress } from '@mui/material';
import CheckIcon from '@mui/icons-material/Check';
import SaveIcon from '@mui/icons-material/Save';

interface StartIconConfigProps {
  hasLabel: boolean;
  loading: boolean;
  succeeded: boolean;
}

interface Props {
  iconSize: number;
  isSmall: boolean;
  smallIconSize: number;
  startIconConfig: StartIconConfigProps;
}

const StartIcon = ({
  isSmall,
  startIconConfig,
  smallIconSize,
  iconSize,
}: Props): JSX.Element | null =>
  cond<StartIconConfigProps, JSX.Element | null>([
    [pipe(propEq('hasLabel', true), not), always(null)],
    [propEq('succeeded', true), always(<CheckIcon />)],
    [
      propEq('loading', true),
      always(
        <CircularProgress
          color="inherit"
          size={isSmall ? smallIconSize : iconSize}
        />,
      ),
    ],
    [T, always(<SaveIcon />)],
  ])(startIconConfig);

export default StartIcon;
