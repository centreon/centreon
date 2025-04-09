import { Typography } from '@mui/material';
import { memo } from 'react';
import {
  labelFilterRessources,
  labelFilteredResources,
  labelNumerOfLines
} from '../../translatedLabels';
import useExportCsvStyles from './exportCsv.styles';
import { LoadingSkeleton } from '@centreon/ui';

interface Props {
  disableExport: boolean;
  numberExportedLines: string;
  isLoading: boolean;
}

const InformationsLine = ({
  numberExportedLines,
  disableExport,
  isLoading
}: Props) => {
  const { classes, cx } = useExportCsvStyles();

  const description = `${labelNumerOfLines}: ${numberExportedLines}`;

  return (
    <div className={classes.information}>
      <Typography variant="body2">{labelFilteredResources}</Typography>
      {!isLoading ? (
        <Typography
          variant="body2"
          className={cx(classes.lines, { [classes.error]: disableExport })}
        >
          {description}
        </Typography>
      ) : (
        <LoadingSkeleton variant="text" />
      )}
      <Typography variant="body2">
        {disableExport && !isLoading && labelFilterRessources}
      </Typography>
    </div>
  );
};

export default memo(InformationsLine);
