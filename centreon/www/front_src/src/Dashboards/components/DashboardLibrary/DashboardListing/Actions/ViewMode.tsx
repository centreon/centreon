import ListIcon from '@mui/icons-material/FormatListBulleted';
import CardsIcon from '@mui/icons-material/GridViewOutlined';
import { Box } from '@mui/material';

import { IconButton } from '@centreon/ui';

import { useAtom } from 'jotai';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

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
      changeMode: () => setViewMode(ViewModeType.Cards),
      Icon: CardsIcon,
      label: labelCardsView,
      mode: ViewModeType.Cards
    },
    {
      changeMode: () => setViewMode(ViewModeType.List),
      Icon: ListIcon,
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
            onClick={changeMode}
            title={t(label)}
          >
            <Icon />
          </IconButton>
        );
      })}
    </Box>
  );
};

export default ViewMode;
