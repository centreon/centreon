import { useTranslation } from 'react-i18next';

import FavoriteIcon from '@mui/icons-material/Bookmark';

import { Tooltip } from '@centreon/ui/components';

import {
  labelMarkedAsFavorite,
  labelNotMarkedAsFavorite
} from '../translatedLabels';

interface Props {
  isFavorite?: boolean;
}

const Favorite = ({ isFavorite }: Props): JSX.Element => {
  const { t } = useTranslation();

  return (
    <Tooltip
      followCursor={false}
      label={t(isFavorite ? labelMarkedAsFavorite : labelNotMarkedAsFavorite)}
      position="top"
    >
      <div>
        <FavoriteIcon
          color={isFavorite ? 'success' : 'disabled'}
          fontSize="small"
        />
      </div>
    </Tooltip>
  );
};

export default Favorite;
