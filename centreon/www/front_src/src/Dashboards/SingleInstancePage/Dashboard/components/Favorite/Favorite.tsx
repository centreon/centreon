import { useTranslation } from 'react-i18next';
import { useAtomValue } from 'jotai';

import FavoriteIcon from '@mui/icons-material/Favorite';

import { IconButton, Tooltip } from '@centreon/ui/components';
import { isOnPublicPageAtom } from '@centreon/ui-context';

import { labelFavorite } from '../../translatedLabels';

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
    <Tooltip followCursor={false} label={t(labelFavorite)} placement="top">
      <div>
        <IconButton
          className={classes.button}
          icon={<FavoriteIcon color={isFavorite ? 'success' : 'disabled'} />}
          size="medium"
          onClick={toggleFavorite}
        />
      </div>
    </Tooltip>
  );
};

export default Favorite;
