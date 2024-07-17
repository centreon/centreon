import { equals, isNil } from 'ramda';
import { useAtomValue } from 'jotai';

import { useTheme } from '@mui/material';

import { detailsAtom, panelWidthStorageAtom } from '../../detailsAtoms';
import DetailsLoadingSkeleton from '../../LoadingSkeleton';

import SortableCards from './SortableCards';
import DetailsActions from './DetailsActions';

const DetailsTab = (): JSX.Element => {
  const theme = useTheme();
  const details = useAtomValue(detailsAtom);
  const panelWidth = useAtomValue(panelWidthStorageAtom);
  const loading = isNil(details) || equals(panelWidth, 0);
  const panelPadding = parseInt(theme.spacing(4), 10);

  console.log({ loading });

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
