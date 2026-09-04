// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import FavoriteIcon from '@mui/icons-material/Favorite';
import FavoriteBorderIcon from '@mui/icons-material/FavoriteBorder';

import { IconButton } from '@centreon/ui';

import { useAtom } from 'jotai';
import { memo } from 'react';
import { useTranslation } from 'react-i18next';

import { labelFavoriteFilter } from '../../../../../translatedLabels';
import { onlyFavoriteDashboardsAtom } from './atoms';
import useFavoriteFilterStyles from './favoriteFilter.styles';

const FavoriteFilter = () => {
  const { classes, cx } = useFavoriteFilterStyles();
  const { t } = useTranslation();
  const [checked, setChecked] = useAtom(onlyFavoriteDashboardsAtom);

  const toggle = (): void => setChecked((current) => !current);

  return (
    <IconButton
      ariaLabel={t(labelFavoriteFilter)}
      className={cx(classes.container, checked && classes.containerActive)}
      onClick={toggle}
      title={t(labelFavoriteFilter)}
    >
      {checked ? (
        <FavoriteIcon className={classes.iconActive} fontSize="small" />
      ) : (
        <FavoriteBorderIcon fontSize="small" />
      )}
    </IconButton>
  );
};

export default memo(FavoriteFilter);
