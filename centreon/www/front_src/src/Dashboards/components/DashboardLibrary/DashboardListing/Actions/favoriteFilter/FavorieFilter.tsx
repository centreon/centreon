import { Checkbox } from '@centreon/ui';

import { useAtom } from 'jotai';
import { memo } from 'react';
import { useTranslation } from 'react-i18next';

import { labelFavoriteFilter } from '../../../../../translatedLabels';
import { onlyFavoriteDashboardsAtom } from './atoms';
import useFavoriteFilterStyles from './favoriteFilter.styles';

const FavoriteFilter = () => {
  const { classes } = useFavoriteFilterStyles();
  const { t } = useTranslation();
  const [checked, setChecked] = useAtom(onlyFavoriteDashboardsAtom);

  const labelProps = {
    classes: { root: classes.label },
    noWrap: true,
    variant: 'body2' as const
  };
  const onChange = (event) => {
    setChecked(event?.target?.checked);
  };

  return (
    <Checkbox
      checked={checked}
      className={classes.container}
      label={t(labelFavoriteFilter)}
      labelProps={labelProps}
      onChange={onChange}
    />
  );
};

export default memo(FavoriteFilter);
