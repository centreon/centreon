import { Box } from '@mui/material';
import { useTranslation } from 'react-i18next';
import { labelAdd } from '../translatedLabels';
import { useActionsStyles } from './Actions.styles';
import AddButton from './AddButton';
import Search from './Search';

interface Props {
  labels: {
    search: string;
    add: string;
  };
  filters: JSX.Element;
}

const Actions = ({ labels, filters }: Props): JSX.Element => {
  const { classes } = useActionsStyles();
  const { t } = useTranslation();

  return (
    <Box className={classes.actions}>
      <AddButton label={t(labelAdd)} />
      <div className={classes.filters}>
        <Search label={labels.search} filters={filters} />
      </div>
    </Box>
  );
};

export default Actions;
