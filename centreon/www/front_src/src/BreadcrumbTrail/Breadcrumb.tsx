import { Chip, Link } from '@mui/material';

import { useTranslation } from 'react-i18next';
import { Link as RouterLink } from 'react-router';
import { makeStyles } from 'tss-react/mui';

import { Breadcrumb as BreadcrumbModel } from './models';

const useStyles = makeStyles()((theme) => ({
  link: {
    '&:hover': {
      textDecoration: 'underline'
    },
    color: theme.palette.text.secondary,
    display: 'block',
    fontSize: '0.75rem',
    fontWeight: theme.typography.fontWeightMedium,
    lineHeight: 1,
    textDecoration: 'none',
    whiteSpace: 'nowrap'
  },
  linkLast: {
    color: theme.palette.primary.main
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
  const { classes, cx } = useStyles();
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
        className={cx(classes.link, { [classes.linkLast]: last })}
        component={RouterLink}
        to={breadcrumb.link}
      >
        {t(breadcrumb.label)}
      </Link>
      {optionalLabel}
    </div>
  );
};

export default Breadcrumb;
