import { useTranslation } from 'react-i18next';
import FavoriteIcon from '@mui/icons-material/Favorite';

import {
  labelMarkAsFavorite,
  labelUnmarkAsFavorite
} from '../../translatedLabels';
import { IconButton } from '@centreon/ui';
import { useFavorite } from './useFavorite';

interface Props {
  isFavorite?: boolean;
  dashboardId: number;
}

const Favorite = ({ isFavorite, dashboardId }: Props): JSX.Element => {
  const { t } = useTranslation();

  const {toggleFavorite} = useFavorite({isFavorite, dashboardId});


  return (
      <IconButton
        title={t(isFavorite ? labelUnmarkAsFavorite : labelMarkAsFavorite)}
        onClick={toggleFavorite}
        data-testid="favorite-icon"
        data-favorite= {isFavorite}
      >
          <FavoriteIcon  fontSize='small' color={isFavorite ? "success" : "disabled"} />
      </IconButton>
  );
};

export default Favorite;