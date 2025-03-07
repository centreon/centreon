import { Typography } from '@mui/material';
import { memo } from 'react';
import {
  labelFilterRessources,
  labelFilteredResources,
  labelNumerOfLines
} from '../../translatedLabels';
import useExportCsvStyles from './exportCsv.styles';

interface Props {
  disableExport: boolean;
  numberExportedLines: string;
}

const InformationsLine = ({ numberExportedLines, disableExport }: Props) => {
  const { classes, cx } = useExportCsvStyles();

  const description = `${labelNumerOfLines}' '${numberExportedLines}`;

  return (
    <div className={classes.information}>
      <Typography variant="body2">{labelFilteredResources}</Typography>
      <Typography
        variant="body2"
        className={cx(classes.lines, { [classes.error]: disableExport })}
      >
        {description}
      </Typography>
      <Typography variant="body2">
        {disableExport && labelFilterRessources}
      </Typography>
    </div>
  );
};

export default memo(InformationsLine);
