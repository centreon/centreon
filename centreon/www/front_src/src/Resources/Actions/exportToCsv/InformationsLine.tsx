import { LoadingSkeleton } from '@centreon/ui';
import { Typography } from '@mui/material';
import { memo } from 'react';
import { useTranslation } from 'react-i18next';
import {
  labelFilterRessources,
  labelFilteredResources,
  labelNumerOfLines
} from '../../translatedLabels';
import useExportCsvStyles from './exportCsv.styles';
import { maxResources } from './useExportCsv';

interface Props {
  hasReachedMaximumLinesToExport: boolean;
  numberExportedLines: number;
  isLoading: boolean;
}

const InformationsLine = ({
  numberExportedLines,
  hasReachedMaximumLinesToExport,
  isLoading
}: Props) => {
  const { classes, cx } = useExportCsvStyles();
  const { t } = useTranslation();

  const description = (
    <span>
      {t(labelNumerOfLines)}{' '}
      <span className={cx({ [classes.error]: hasReachedMaximumLinesToExport })}>
        {numberExportedLines}
      </span>{' '}
      / {maxResources}
    </span>
  );

  return (
    <div className={classes.information}>
      <Typography variant="body2">{t(labelFilteredResources)}</Typography>
      {!isLoading ? (
        <Typography variant="body2" className={classes.lines}>
          {description}
        </Typography>
      ) : (
        <LoadingSkeleton variant="text" />
      )}
      <Typography variant="body2">
        {hasReachedMaximumLinesToExport && t(labelFilterRessources)}
      </Typography>
    </div>
  );
};

export default memo(InformationsLine);
