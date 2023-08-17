import { useTranslation } from 'react-i18next';

import IconAcknowledge from '@mui/icons-material/Person';
import { useTheme } from '@mui/material';

import { labelAcknowledgement } from '../../../translatedLabels';
import EventAnnotations from '../EventAnnotations';
import { Args } from '../models';

const AcknowledgementAnnotations = (props: Args): JSX.Element => {
  const { t } = useTranslation();
  const theme = useTheme();

  const color = theme.palette.action.acknowledged;

  return (
    <EventAnnotations
      Icon={IconAcknowledge}
      ariaLabel={t(labelAcknowledgement)}
      color={color}
      type="acknowledgement"
      {...props}
    />
  );
};

export default AcknowledgementAnnotations;
