import { useAtomValue } from 'jotai';
import { identity } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { statsDerivedAtom } from '../atoms';
import { Labels } from '../models';

interface Props {
  labels: Labels['list'];
}

const Stats = ({ labels }: Props): JSX.Element => {
  const { t } = useTranslation();
  const { hasStats, addedItems, updatedItems, removedItems } =
    useAtomValue(statsDerivedAtom);

  if (!hasStats) {
    return null;
  }

  const statsLabels = [
    addedItems && `${addedItems} ${t(labels.added).toLocaleLowerCase()}`,
    updatedItems && `${updatedItems} ${t(labels.updated).toLocaleLowerCase()}`,
    removedItems && `${removedItems} ${t(labels.removed).toLocaleLowerCase()}`
  ].filter(identity);

  return (
    <Typography textAlign="right">
      <strong>{statsLabels.join(' | ')}</strong>
    </Typography>
  );
};

export default Stats;
