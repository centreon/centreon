import { ReactNode } from 'react';

import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Card, CardActionArea } from '@mui/material';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';

import { Tooltip } from '@centreon/ui/components';

import { editProperties } from '../../../../hooks/useCanEditDashboard';

import { useStyles } from './DisplayType.styles';

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
  const { classes } = useStyles();
  const { t } = useTranslation();

  const isSelected = equals(value, type);

  const { canEditField } = editProperties.useCanEditProperties();

  return (
    <Tooltip followCursor={false} label={t(label)} position="top">
      <Card
        className={classes.typeOption}
        data-disabled={!canEditField}
        data-selected={isSelected}
        data-type={type}
        key={type}
      >
        <CardActionArea
          className={classes.typeOption}
          disabled={!canEditField}
          onClick={changeType(type)}
        >
          <div className={classes.typeIcon}>{icon}</div>
          {isSelected && (
            <div className={classes.typeSelected}>
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
