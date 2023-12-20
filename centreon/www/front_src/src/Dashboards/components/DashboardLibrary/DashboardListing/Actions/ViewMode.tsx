import { useTranslation } from 'react-i18next';
import { useAtom } from 'jotai';
import { equals } from 'ramda';

import { Box } from '@mui/material';
import ListIcon from '@mui/icons-material/FormatListBulleted';
import CardsIcon from '@mui/icons-material/GridViewOutlined';

import { IconButton } from '@centreon/ui';

import { labelCardsView, labelListView } from '../translatedLabels';
import { viewModeAtom } from '../atom';
import { ViewMode as ViewModeType } from '../models';

import { useActionsStyles } from './useActionsStyles';

const ViewMode = (): JSX.Element => {
  const { classes } = useActionsStyles();

  const { t } = useTranslation();
  const [viewMode, setViewMode] = useAtom(viewModeAtom);

  const actions = [
    {
      Icon: CardsIcon,
      label: labelCardsView,
      mode: ViewModeType.Cards,
      onClick: () => setViewMode(ViewModeType.Cards)
    },
    {
      Icon: ListIcon,
      label: labelListView,
      mode: ViewModeType.List,
      onClick: () => setViewMode(ViewModeType.List)
    }
  ];

  return (
    <Box className={classes.viweMode}>
      {actions.map(({ label, Icon, onClick, mode }) => {
        return (
          <IconButton
            ariaLabel={t(label)}
            color={equals(mode, viewMode) ? 'primary' : 'default'}
            data-selected={equals(mode, viewMode)}
            key={label}
            title={t(label)}
            onClick={onClick}
          >
            <Icon />
          </IconButton>
        );
      })}
    </Box>
  );
};

export default ViewMode;
