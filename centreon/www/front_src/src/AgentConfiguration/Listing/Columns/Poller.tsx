import { usePluralizedTranslation } from '@centreon/ui';
import { Box } from '@mui/material';
import { isNil } from 'ramda';
import { labelPoller } from '../../translatedLabels';

const Poller = ({ row }) => {
  const { pluralizedT } = usePluralizedTranslation();

  const isSubNested = isNil(row.pollers);

  return (
    <Box sx={{ pl: isSubNested ? 3 : 0 }}>
      {isSubNested
        ? row.name
        : `${row.pollers.length} ${pluralizedT({ count: row.pollers.length, label: labelPoller.toLocaleLowerCase() })}`}
    </Box>
  );
};

export default Poller;
