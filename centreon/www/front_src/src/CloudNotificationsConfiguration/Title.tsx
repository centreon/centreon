import { makeStyles } from 'tss-react/mui';
import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { labelNotificationsManagement } from './translatedLabels';

interface Props {
  className?: string;
}

const useStyle = makeStyles()((theme) => ({
  title: {
    borderBottom: `1px solid ${theme.palette.primary.main}`,
    fontWeight: theme.typography.fontWeightBold,
    marginTop: theme.spacing(1.5),
    paddingBottom: theme.spacing(1.5)
  }
}));

const Title = ({ className }: Props): JSX.Element => {
  const { cx, classes } = useStyle();
  const { t } = useTranslation();

  return (
    <Typography className={cx(classes.title, className)} variant="h5">
      {t(labelNotificationsManagement)}
    </Typography>
  );
};

export default Title;
