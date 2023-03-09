import { makeStyles } from 'tss-react/mui';
import { isNil } from 'ramda';

import { Typography } from '@mui/material';

import { Image, LoadingSkeleton } from '@centreon/ui';

import { LoginPageCustomisation } from './models';

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

  const hasIconSource = !isNil(loginPageCustomisation.iconSource);

  return (
    <div>
      {hasIconSource && (
        <Image
          alt="login icon platform"
          className={classes.loginElementItem}
          fallback={<LoadingSkeleton height={50} width={250} />}
          height={50}
          imagePath={loginPageCustomisation.iconSource || ''}
        />
      )}
      <Typography className={classes.loginElementItem} variant="h4">
        {loginPageCustomisation.platformName}
      </Typography>
    </div>
  );
};

export default LoginHeader;
