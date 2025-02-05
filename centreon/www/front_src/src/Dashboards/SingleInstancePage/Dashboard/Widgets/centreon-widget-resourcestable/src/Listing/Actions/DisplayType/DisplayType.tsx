import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Box, Typography } from '@mui/material';

import { DisplayType as DisplayTypeEnum } from '../../models';
import {
  labelAll,
  labelDisplayView,
  labelViewByHost,
  labelViewByService
} from '../../translatedLabels';

import Option from './Option';
import { useStyles } from './displayType.styles';

const options = [
  {
    option: DisplayTypeEnum.All,
    title: labelAll
  },
  {
    option: DisplayTypeEnum.Host,
    title: labelViewByHost
  },
  {
    option: DisplayTypeEnum.Service,
    title: labelViewByService
  }
];

interface Props {
  displayType: DisplayTypeEnum;
  hasMetaService: boolean;
  setPanelOptions: (panelOptions) => void;
  isOpenTicketEnabled: boolean;
}

const DisplayType = ({
  displayType,
  setPanelOptions,
  hasMetaService,
  isOpenTicketEnabled
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const changeDisplayType = (option) => (): void => {
    setPanelOptions?.({ displayType: option });
  };

  const getDisabled = (option): boolean => {
    return (
      (hasMetaService && equals(option, DisplayTypeEnum.Host)) ||
      (isOpenTicketEnabled &&
        (equals(option, DisplayTypeEnum.Host) ||
          equals(option, DisplayTypeEnum.All)))
    );
  };

  return (
    <Box className={classes.container} data-testid="tree view">
      <Typography className={classes.text}>{t(labelDisplayView)}</Typography>
      {options.map(({ option, title }) => {
        return (
          <Option
            changeDisplayType={changeDisplayType(option)}
            disabled={getDisabled(option)}
            isActive={equals(displayType, option) && !getDisabled(option)}
            key={option}
            option={option}
            title={t(title)}
          />
        );
      })}
    </Box>
  );
};

export default DisplayType;
