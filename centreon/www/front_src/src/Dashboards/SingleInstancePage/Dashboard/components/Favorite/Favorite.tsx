import { useTranslation } from 'react-i18next';
import { useAtomValue } from 'jotai';

import FavoriteAdd from '@mui/icons-material/BookmarkAdd';
import FavoriteRemove from '@mui/icons-material/BookmarkRemove';

import { IconButton, Tooltip } from '@centreon/ui/components';
import { isOnPublicPageAtom } from '@centreon/ui-context';

import {
  labelMarkAsFavorite,
  labelUnmarkAsFavorite
} from '../../translatedLabels';

import { useFavorite } from './useFavorite';
import { useFavoriteStyle } from './Favorite.styles';

const Favorite = (): JSX.Element | null => {
  const { t } = useTranslation();
  const { classes } = useFavoriteStyle();
  const isOnPublicPage = useAtomValue(isOnPublicPageAtom);
  const { isFavorite, toggleFavorite } = useFavorite();

  if (isOnPublicPage) {
    return null;
  }

  return (
    <Tooltip
      followCursor={false}
      label={t(isFavorite ? labelUnmarkAsFavorite : labelMarkAsFavorite)}
      placement="top"
    >
      <div>
        {isFavorite ? (
          <IconButton
            className={classes.button}
            icon={<FavoriteRemove color="success" />}
            size="medium"
            onClick={toggleFavorite}
          />
        ) : (
          <IconButton
            className={classes.button}
            icon={<FavoriteAdd color="disabled" />}
            size="medium"
            onClick={toggleFavorite}
          />
        )}
      </div>
    </Tooltip>
  );
};

export default Favorite;
