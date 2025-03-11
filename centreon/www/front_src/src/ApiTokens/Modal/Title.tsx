import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import {
  labelCreateNewToken,
  labelSecurityToken,
  labelTokenCreated
} from '../translatedLabels';

import { useStyles } from './Form.styles';

interface Props {
  token?: string;
}

const Title = ({ token }: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  return (
    <div>
      {token ? (
        <div className={classes.containerTitle}>
          <Typography className={classes.title} variant="h6">
            {t(labelTokenCreated)}
          </Typography>
          <Typography variant="subtitle2">{t(labelSecurityToken)}</Typography>
        </div>
      ) : (
        <Typography className={classes.title} variant="h6">
          {t(labelCreateNewToken)}
        </Typography>
      )}
    </div>
  );
};

export default Title;
