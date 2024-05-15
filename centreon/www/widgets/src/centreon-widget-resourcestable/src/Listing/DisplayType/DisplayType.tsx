import { useTranslation } from 'react-i18next';

import { Box, Typography } from '@mui/material';

import {
  labelDisplayView,
  labelViewByHost,
  labelViewByService,
  labelAll
} from '../translatedLabels';
import { DisplayType } from '../models';

import viewByServiceActive from './icons/view_service_actif.svg';
import viewByServiceInactive from './icons/view_service_inactif.svg';
import viewByHostActive from './icons/view_host_actif.svg';
import viewByHostInactive from './icons/view_host_inactif.svg';
import viewByAllInactive from './icons/view_all_inactif.svg';
import viewByAllActive from './icons/view_all_actif.svg';
import Option from './Option';
import { useStyles } from './displayType.styles';

const options = [
  {
    IconOnActive: viewByAllActive,
    IconOnInactive: viewByAllInactive,
    option: DisplayType.All,
    title: labelAll
  },
  {
    IconOnActive: viewByHostActive,
    IconOnInactive: viewByHostInactive,
    option: DisplayType.Host,
    title: labelViewByHost
  },
  {
    IconOnActive: viewByServiceActive,
    IconOnInactive: viewByServiceInactive,
    option: DisplayType.Service,
    title: labelViewByService
  }
];

interface Props {
  displayType: DisplayType;
  setPanelOptions: (panelOptions) => void;
}

const DisplayTypeComponent = ({
  displayType,
  setPanelOptions
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  return (
    <Box className={classes.container} data-testid="tree view">
      <Typography className={classes.text}>{t(labelDisplayView)}</Typography>
      {options.map(({ option, title, IconOnActive, IconOnInactive }) => {
        return (
          <Option
            IconOnActive={IconOnActive}
            IconOnInactive={IconOnInactive}
            displayType={displayType}
            key={option}
            option={option}
            setPanelOptions={setPanelOptions}
            title={title}
          />
        );
      })}
    </Box>
  );
};

export default DisplayTypeComponent;
