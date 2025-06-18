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
    variant: 'body2' as const,
    noWrap: true
  };
  const onChange = (event) => {
    setChecked(event?.target?.checked);
  };

  return (
    <Checkbox
      label={t(labelFavoriteFilter)}
      onChange={onChange}
      labelProps={labelProps}
      checked={checked}
      className={classes.container}
    />
  );
};

export default memo(FavoriteFilter);
