import { Typography } from '@mui/material';

import { useTranslation } from 'react-i18next';

import type { Actor } from './models';
import { labelSystem, labelUnknown } from './translatedLabels';

interface Props {
  actor?: Actor | null;
}

// Changes made by the platform have no actor, deleted users have no name
const Author = ({ actor }: Props): JSX.Element => {
  const { t } = useTranslation();

  const getName = (): string => {
    if (!actor) {
      return t(labelSystem);
    }

    return actor.name || t(labelUnknown);
  };

  return (
    <Typography className="truncate" component="span" variant="body2">
      {getName()}
    </Typography>
  );
};

export default Author;
