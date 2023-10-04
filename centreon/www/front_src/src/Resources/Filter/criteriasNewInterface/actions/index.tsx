import { makeStyles } from 'tss-react/mui';
import { useTranslation } from 'react-i18next';

import { Button, Grid } from '@mui/material';

import { labelClear, labelSearch } from '../../../translatedLabels';

import Save from './Save';

const useStyles = makeStyles()((theme) => ({
  actions: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginTop: theme.spacing(1)
  }
}));

const Actions = ({ onSearch, onClear }) => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  return (
    <Grid container item className={classes.actions} spacing={1}>
      <Grid item data-testid={labelClear} sx={{ flex: 1 }}>
        <Button
          color="primary"
          data-testid="Filter Clear"
          size="small"
          onClick={onClear}
        >
          {t(labelClear)}
        </Button>
      </Grid>
      <Grid item style={{ display: 'flex' }}>
        <Save />

        <Button
          color="primary"
          data-testid="Filter Search"
          size="small"
          variant="contained"
          onClick={onSearch}
        >
          {t(labelSearch)}
        </Button>
      </Grid>
    </Grid>
  );
};

export default Actions;
