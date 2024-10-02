import { Tune } from '@mui/icons-material';
import PopoverMenu from '../../../PopoverMenu';
import { useActionsStyles } from './Actions.styles';

interface Props {
  label: string;
  filters: JSX.Element;
}

const Filters = ({ label, filters }: Props): JSX.Element => {
  const { classes } = useActionsStyles();

  return (
    <PopoverMenu
      title={label}
      icon={<Tune />}
      tooltipClassName={classes.tooltipFilters}
    >
      {filters}
    </PopoverMenu>
  );
};

export default Filters;
