import { ReactNode } from 'react';

import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Card, CardActionArea } from '@mui/material';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';

import { Tooltip } from '@centreon/ui/components';

import { useCanEditProperties } from '../../../../hooks/useCanEditDashboard';

import { useGraphTypeStyles } from './SingleMetricGraphType.styles';

interface Props {
  changeType: (type: string) => () => void;
  icon: ReactNode;
  label: string;
  type: string;
  value: string;
}

const OptionCard = ({
  changeType,
  type,
  icon,
  value,
  label
}: Props): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useGraphTypeStyles();

  const isSelected = equals(value, type);

  const { canEditField } = useCanEditProperties();

  return (
    <Tooltip followCursor={false} label={t(label)} position="top">
      <Card
        className={classes.graphTypeOption}
        data-disabled={!canEditField}
        data-selected={isSelected}
        data-type={type}
        key={type}
      >
        <CardActionArea
          className={classes.graphTypeOption}
          disabled={!canEditField}
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
    </Tooltip>
  );
};

export default OptionCard;
