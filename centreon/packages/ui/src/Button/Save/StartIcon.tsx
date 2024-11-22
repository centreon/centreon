import { T, always, cond, not, pipe, propEq } from 'ramda';

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
  cond<Array<StartIconConfigProps>, JSX.Element | null>([
    [pipe(propEq(true, 'hasLabel'), not), always(null)],
    [propEq(true, 'succeeded'), always(<CheckIcon />)],
    [propEq(true, 'loading'), always(<SaveIcon />)],
    [T, always(<SaveIcon />)]
  ])(startIconConfig);

export default StartIcon;
