import { Typography } from '@mui/material';
import { useAtomValue } from 'jotai';
import { memo } from 'react';
import { listingAtom } from '../../Listing/listingAtoms';
import {
  labelFilterRessources,
  labelFilteredResources,
  labelNumerOfLines
} from '../../translatedLabels';
import useExportCsvStyles from './exportCsv.styles';

interface Props {
  isAllPagesChecked: boolean;
}

const maxResources = 10000;

const InformationsLine = ({ isAllPagesChecked }: Props) => {
  const { classes, cx } = useExportCsvStyles();
  const listing = useAtomValue(listingAtom);
  const filteredCurrentLines = `${listing?.result?.length}/${maxResources}`;
  const currentLines = `${listing?.meta?.total} / ${maxResources}`;
  const displayWarningMsg = isAllPagesChecked
    ? listing?.meta?.total > maxResources
    : listing?.result?.length > maxResources;

  return (
    <div className={classes.information}>
      <Typography variant="body2">{labelFilteredResources}</Typography>
      <Typography
        variant="body2"
        className={cx(classes.lines, { [classes.error]: displayWarningMsg })}
      >
        {labelNumerOfLines}:{' '}
        {isAllPagesChecked ? currentLines : filteredCurrentLines}
      </Typography>
      <Typography variant="body2">
        {displayWarningMsg && labelFilterRessources}
      </Typography>
    </div>
  );
};

export default memo(InformationsLine);
