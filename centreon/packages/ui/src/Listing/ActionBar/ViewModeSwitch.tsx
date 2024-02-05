import { useTranslation } from 'react-i18next';
import { equals } from 'ramda';

import { Box } from '@mui/material';
import ListIcon from '@mui/icons-material/FormatListBulleted';
import CardsIcon from '@mui/icons-material/GridViewOutlined';

import { IconButton } from '@centreon/ui';

import { labelCardsView, labelListView } from '../translatedLabels';

import { useViewModeStyles } from './ActionBar.styles';

enum ViewModeType {
  Grid = 'Grid',
  List = 'List'
}

interface Props {
  setViewMode: (viewMode) => void;
  viewMode: ViewModeType;
}

const ViewModeSwitch = ({ viewMode, setViewMode }: Props): JSX.Element => {
  const { classes } = useViewModeStyles();

  const { t } = useTranslation();

  const actions = [
    {
      Icon: CardsIcon,
      changeMode: () => setViewMode(ViewModeType.Grid),
      label: labelCardsView,
      mode: ViewModeType.Grid
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

export default ViewModeSwitch;
