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
      icon={<Tune />}
      tooltipClassName={classes.tooltipFilters}
    >
      {isValidElement(filters) ? filters : <div />}
    </PopoverMenu>
  );
};

export default Filters;
