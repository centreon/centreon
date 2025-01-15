import { useTranslation } from 'react-i18next';

import { useTheme } from '@mui/material';

import { DowntimeIcon } from '../../../../../Icon';
import { labelDowntime } from '../../../translatedLabels';
import EventAnnotations from '../EventAnnotations';
import { Args } from '../models';

const DowntimeAnnotations = (props: Args): JSX.Element => {
  const { t } = useTranslation();
  const theme = useTheme();

  const color = theme.palette.action.inDowntime;

  return (
    <EventAnnotations
      Icon={DowntimeIcon}
      ariaLabel={t(labelDowntime)}
      color={color}
      type="downtime"
      {...props}
    />
  );
};

export default DowntimeAnnotations;
