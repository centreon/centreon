import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { alpha, Paper, Typography } from '@mui/material';

import { labelInvalidLicense } from './translatedLabels';

const useStyles = makeStyles()((theme) => ({
  divWrapper: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'column'
  },
  paper: {
    backgroundColor: alpha(theme.palette.error.main, 0.15),
    marginTop: theme.spacing(8),
    textAlign: 'center',
    width: theme.spacing(50)
  }
}));

interface Props {
  className?: string;
  label?: string;
}

const LicenseMessage = ({ label, className }: Props): JSX.Element => {
  const { classes, cx } = useStyles();
  const { t } = useTranslation();

  return (
    <div className={classes.divWrapper}>
      <Paper className={cx(classes.paper, className)}>
        <Typography component="p">{label || t(labelInvalidLicense)}</Typography>
      </Paper>
    </div>
  );
};

export default LicenseMessage;
