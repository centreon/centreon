import { useTheme } from '@mui/material';

import { FlappingIcon } from '@centreon/ui';

import { Tooltip } from '@centreon/ui/components';
import { useTranslation } from 'react-i18next';
import Chip from '.';
import { labelResourceFlapping } from '../translatedLabels';

const FlappingChip = (): JSX.Element => {
  const theme = useTheme();
  const { t } = useTranslation();

  return (
    <Tooltip label={t(labelResourceFlapping)}>
      <div>
        <Chip
          color={theme.palette.action.inFlapping}
          icon={<FlappingIcon fontSize="small" />}
        />
      </div>
    </Tooltip>
  );
};

export default FlappingChip;
