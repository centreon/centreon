import { always, cond, not, pipe, propEq, T } from 'ramda';

import CheckIcon from '@mui/icons-material/Check';
import SaveIcon from '@mui/icons-material/Save';

interface StartIconConfigProps {
  hasLabel: boolean;
  loading: boolean;
  succeeded: boolean;
}

interface Props {
  startIconConfig: StartIconConfigProps;
}

const StartIcon = ({ startIconConfig }: Props): JSX.Element | null =>
  cond<StartIconConfigProps, JSX.Element | null>([
    [pipe(propEq('hasLabel', true), not), always(null)],
    [propEq('succeeded', true), always(<CheckIcon />)],
    [propEq('loading', true), always(<SaveIcon />)],
    [T, always(<SaveIcon />)]
  ])(startIconConfig);

export default StartIcon;
