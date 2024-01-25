import { useTranslation } from 'react-i18next';
import { useAtomValue } from 'jotai';
import { identity } from 'ramda';

import { Typography } from '@mui/material';

import { Labels } from '../models';
import { statsDerivedAtom } from '../atoms';

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
