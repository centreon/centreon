import { makeStyles } from 'tss-react/mui';
import { isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { Image, LoadingSkeleton } from '@centreon/ui';

import { LoginPageCustomisation } from './models';
import { labelCentreonLogo } from './translatedLabels';

interface Props {
  loginPageCustomisation: LoginPageCustomisation;
}

const useStyles = makeStyles()({
  loginElementItem: {
    display: 'inline',
    verticalAlign: 'middle'
  }
});

const LoginHeader = ({ loginPageCustomisation }: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const hasIconSource = !isNil(loginPageCustomisation.iconSource);

  return (
    <div id="loginHeader">
      {hasIconSource && (
        <Image
          alt={t(labelCentreonLogo)}
          className={classes.loginElementItem}
          fallback={<LoadingSkeleton height={50} width={250} />}
          height={50}
          imagePath={loginPageCustomisation.iconSource || ''}
        />
      )}
      {loginPageCustomisation?.platformName && (
        <Typography className={classes.loginElementItem} variant="h4">
          {loginPageCustomisation.platformName}
        </Typography>
      )}
    </div>
  );
};

export default LoginHeader;
