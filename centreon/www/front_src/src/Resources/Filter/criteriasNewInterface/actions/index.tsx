import { ReactNode } from 'react';

import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { Button, Grid } from '@mui/material';

import { labelClear, labelSearch } from '../../../translatedLabels';

const useStyles = makeStyles()((theme) => ({
  actions: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginTop: theme.spacing(1)
  },
  clear: {
    justifyContent: 'start'
  },
  rightContainer: {
    display: 'flex',
    gap: 4
  }
}));

interface Props {
  onClear: () => void;
  onSearch: () => void;
  save?: ReactNode;
}

const Actions = ({ onSearch, onClear, save }: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  return (
    <Grid container item className={classes.actions} spacing={1}>
      <Grid item data-testid={labelClear} sx={{ flex: 1 }}>
        <Button
          className={classes.clear}
          color="primary"
          data-testid="Filter Clear"
          size="small"
          onClick={onClear}
        >
          {t(labelClear)}
        </Button>
      </Grid>
      <Grid item className={classes.rightContainer}>
        {save}
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
