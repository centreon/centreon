import { useTranslation } from 'react-i18next';
import { Link as RouterLink } from 'react-router-dom';
import { makeStyles } from 'tss-react/mui';

import { Chip, Link } from '@mui/material';

import { Breadcrumb as BreadcrumbModel } from './models';

const useStyles = makeStyles()((theme) => ({
  link: {
    '&:hover': {
      textDecoration: 'underline'
    },
    fontSize: 'small',
    textDecoration: 'none'
  },
  optionalLabel: {
    marginLeft: theme.spacing(1)
  }
}));

interface Props {
  breadcrumb: BreadcrumbModel;
  last: boolean;
}

const Breadcrumb = ({ last, breadcrumb }: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const optionalLabel = breadcrumb.is_react && !!breadcrumb.options && (
    <Chip
      className={classes.optionalLabel}
      color="secondary"
      label={(t(breadcrumb.options) as string).toLocaleUpperCase()}
    />
  );

  return (
    <div>
      <Link
        className={classes.link}
        color={last ? 'primary' : 'inherit'}
        component={RouterLink}
        to={breadcrumb.link}
      >
        {breadcrumb.label}
      </Link>
      {optionalLabel}
    </div>
  );
};

export default Breadcrumb;
