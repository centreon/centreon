import { useTranslation } from 'react-i18next';

import { labelDeleteToken } from '../../../translatedLabels';

import { useStyles } from './deletion.styles';

const Title = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  return <div className={classes.title}>{t(labelDeleteToken)}</div>;
};

export default Title;
