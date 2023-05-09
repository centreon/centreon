import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { useStyles } from './Legend.styles';

interface Props {
  data: string;
  key: string;
  label: string;
}

const LegendContent = ({ data, label, key }: Props): JSX.Element => {
  const { classes } = useStyles({});

  const { t } = useTranslation();

  return (
    <div data-testid={label} key={key}>
      <Typography variant="caption">{t(label)}: </Typography>
      <Typography className={classes.minMaxAvgValue} variant="caption">
        {data}
      </Typography>
    </div>
  );
};

export default LegendContent;
