import { useAtom } from 'jotai';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import ListIcon from '@mui/icons-material/FormatListBulleted';
import CardsIcon from '@mui/icons-material/GridViewOutlined';
import { Box } from '@mui/material';

import { IconButton } from '@centreon/ui';

import { viewModeAtom } from '../atom';
import { ViewMode as ViewModeType } from '../models';
import { labelCardsView, labelListView } from '../translatedLabels';

import { useActionsStyles } from './useActionsStyles';

const ViewMode = (): JSX.Element => {
  const { classes } = useActionsStyles();

  const { t } = useTranslation();
  const [viewMode, setViewMode] = useAtom(viewModeAtom);

  const actions = [
    {
      Icon: CardsIcon,
      changeMode: () => setViewMode(ViewModeType.Cards),
      label: labelCardsView,
      mode: ViewModeType.Cards
    },
    {
      Icon: ListIcon,
      changeMode: () => setViewMode(ViewModeType.List),
      label: labelListView,
      mode: ViewModeType.List
    }
  ];

  return (
    <Box className={classes.viewMode}>
      {actions.map(({ label, Icon, changeMode, mode }) => {
        return (
          <IconButton
            ariaLabel={t(label)}
            color={equals(mode, viewMode) ? 'primary' : 'default'}
            data-selected={equals(mode, viewMode)}
            key={label}
            title={t(label)}
            onClick={changeMode}
          >
            <Icon />
          </IconButton>
        );
      })}
    </Box>
  );
};

export default ViewMode;
