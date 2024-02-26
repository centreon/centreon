import { Chip } from '@mui/material';

import { ItemState } from '../models';

import { useListStyles } from './List.styles';

interface Props {
  label: string;
  state: ItemState;
}

const StateChip = ({ label, state }: Props): JSX.Element => {
  const { classes, cx } = useListStyles();

  return (
    <div className={cx(classes.state)}>
      <Chip
        className={classes.stateChip}
        data-state={state}
        label={label}
        size="small"
        variant="outlined"
      />
    </div>
  );
};

export default StateChip;
