import { Tune } from '@mui/icons-material';
import { isValidElement } from 'react';
import PopoverMenu from '../../../PopoverMenu';
import { useActionsStyles } from './Actions.styles';

interface Props {
  label: string;
  filters: JSX.Element;
}

const Filters: React.FC<Props> = ({ label, filters }: Props): JSX.Element => {
  const { classes } = useActionsStyles();

  return (
    <PopoverMenu
      title={label}
      icon={<Tune fontSize="small" />}
      popperPlacement="bottom-end"
    >
      {isValidElement(filters) ? (
        <div className={classes.filtersContent}>{filters}</div>
      ) : (
        <div />
      )}
    </PopoverMenu>
  );
};

export default Filters;
