import { useAtomValue } from 'jotai';
import { equals, isNil } from 'ramda';

import { useTheme } from '@mui/material';

import DetailsLoadingSkeleton from '../../LoadingSkeleton';
import { detailsAtom, panelWidthStorageAtom } from '../../detailsAtoms';

import DetailsActions from './DetailsActions';
import SortableCards from './SortableCards';

const DetailsTab = (): JSX.Element => {
  const theme = useTheme();
  const details = useAtomValue(detailsAtom);
  const panelWidth = useAtomValue(panelWidthStorageAtom);
  const loading = isNil(details) || equals(panelWidth, 0);
  const panelPadding = Number.parseInt(theme.spacing(4), 10);

  return loading ? (
    <DetailsLoadingSkeleton />
  ) : (
    <div>
      <DetailsActions details={details} />
      <SortableCards details={details} panelWidth={panelWidth - panelPadding} />
    </div>
  );
};

export default DetailsTab;
