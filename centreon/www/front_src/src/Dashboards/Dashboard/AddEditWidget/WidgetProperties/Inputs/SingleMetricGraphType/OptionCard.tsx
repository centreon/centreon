import { ReactNode } from 'react';

import { equals } from 'ramda';

import { Card, CardActionArea } from '@mui/material';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';

import { useGraphTypeStyles } from './SingleMetricGraphType.styles';

interface Props {
  changeType: (type: string) => () => void;
  icon: ReactNode;
  type: string;
  value: string;
}

const OptionCard = ({ changeType, type, icon, value }: Props): JSX.Element => {
  const { classes } = useGraphTypeStyles();

  const isSelected = equals(value, type);

  return (
    <Card
      className={classes.graphTypeOption}
      data-selected={isSelected}
      data-type={type}
      key={type}
    >
      <CardActionArea
        className={classes.graphTypeOption}
        onClick={changeType(type)}
      >
        <div className={classes.graphTypeIcon}>{icon}</div>
        {isSelected && (
          <div className={classes.graphTypeSelected}>
            <CheckCircleIcon
              className={classes.iconSelected}
              color="success"
              fontSize="large"
            />
          </div>
        )}
      </CardActionArea>
    </Card>
  );
};

export default OptionCard;
