import { useTheme } from '@mui/material';

import { FlappingIcon } from '@centreon/ui';
import { Tooltip } from '@centreon/ui/components';

import { useTranslation } from 'react-i18next';

import { labelResourceFlapping } from '../translatedLabels';
import Chip from '.';

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
