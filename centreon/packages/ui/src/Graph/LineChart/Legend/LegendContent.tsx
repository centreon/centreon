import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { useLegendContentStyles } from './Legend.styles';

interface Props {
  data: string;
  label: string;
}

const LegendContent = ({ data, label }: Props): JSX.Element => {
  const { classes, cx } = useLegendContentStyles();

  const { t } = useTranslation();

  return (
    <div className={classes.text} data-testid={label}>
      <Typography className={classes.text} component="span" variant="caption">
        {t(label)}:{' '}
        <Typography
          className={cx(classes.minMaxAvgValue, classes.text)}
          component="span"
          variant="caption"
        >
          {data}
        </Typography>
      </Typography>
    </div>
  );
};

export default LegendContent;
