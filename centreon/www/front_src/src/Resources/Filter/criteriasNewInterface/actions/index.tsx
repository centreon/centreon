import { Button, Grid } from '@mui/material';

import { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

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
    <Grid className={classes.actions} container item spacing={1}>
      <Grid data-testid={labelClear} item sx={{ flex: 1 }}>
        <Button
          className={classes.clear}
          color="primary"
          data-testid="Filter Clear"
          onClick={onClear}
          size="small"
        >
          {t(labelClear)}
        </Button>
      </Grid>
      <Grid className={classes.rightContainer} item>
        {save}
        <Button
          color="primary"
          data-testid="Filter Search"
          onClick={onSearch}
          size="small"
          variant="contained"
        >
          {t(labelSearch)}
        </Button>
      </Grid>
    </Grid>
  );
};

export default Actions;
