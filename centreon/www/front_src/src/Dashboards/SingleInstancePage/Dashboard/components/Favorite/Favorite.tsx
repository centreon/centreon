import FavoriteIcon from '@mui/icons-material/Favorite';

import { IconButton } from '@centreon/ui/components';

import { useFavorite } from './useFavorite';
import { useFavoriteStyle } from './Favorite.styles';

const Favorite = (): JSX.Element => {
  const { classes } = useFavoriteStyle();
  const { isFavorite } = useFavorite();

  return (
    <IconButton
      className={classes.button}
      icon={<FavoriteIcon color={isFavorite ? 'success' : 'disabled'} />}
      size="medium"
    />
  );
};

export default Favorite;
