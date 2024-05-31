import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { useStyles } from './NoResourcesFound.styles';

const NoResourcesFound = ({ label }: { label: string }): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  return (
    <div className={classes.noDataFound}>
      <div className={classes.noDataFoundIconWrapper}>
        <div className={classes.noDataFoundIcon}>!</div>
      </div>
      <Typography variant="h5">{t(label)}</Typography>
    </div>
  );
};

export default NoResourcesFound;
