import parse from 'html-react-parser';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import { Card, CardActionArea, SvgIcon } from '@mui/material';

import { Tooltip } from '@centreon/ui/components';

import { useCanEditProperties } from '../../../../hooks/useCanEditDashboard';

import { useStyles } from './DisplayType.styles';

interface Props {
  changeType: (type: string) => () => void;
  icon: string;
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

  const { canEditField } = useCanEditProperties();

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
          <div className={classes.iconWrapper}>
            <SvgIcon
              className={classes.icon}
              color="inherit"
              data-icon={label}
              viewBox="0 0 60 60"
            >
              {parse(icon)}
            </SvgIcon>
          </div>
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
