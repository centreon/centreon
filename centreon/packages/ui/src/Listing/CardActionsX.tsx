import {
  Delete as DeleteIcon,
  Settings as SettingsIcon,
  Share as ShareIcon
} from '@mui/icons-material';

import { IconButton } from '../components';

const CardActionsX = (): JSX.Element => {
  return (
    <>
      <span>
        <IconButton
          aria-label="delete"
          data-testid="delete"
          icon={<DeleteIcon />}
          size="small"
          variant="ghost"
          onClick={() => undefined}
        />
      </span>
      <span>
        <IconButton
          aria-label="edit access rights"
          data-testid="edit-access-rights"
          icon={<ShareIcon />}
          size="small"
          variant="primary"
          onClick={() => undefined}
        />
        <IconButton
          aria-label="edit"
          data-testid="edit"
          icon={<SettingsIcon />}
          size="small"
          variant="primary"
          onClick={() => undefined}
        />
      </span>
    </>
  );
};

export default CardActionsX;
