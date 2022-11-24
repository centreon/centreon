import dayjs from 'dayjs';
import { useAtom } from 'jotai';

import DeleteIcon from '@mui/icons-material/Delete';
import { IconButton } from '@mui/material';

import { thresholdsAnomalyDetectionDataAtom } from '../anomalyDetectionAtom';

const AnomalyDetectionItemsExclusionPeriods = ({ item }: any): JSX.Element => {
  const [thresholdsAnomalyDetectionData, setThresholdAnomalyDetectionData] =
    useAtom(thresholdsAnomalyDetectionDataAtom);

  const deletePeriod = (): void => {
    const newData =
      thresholdsAnomalyDetectionData.exclusionPeriodsThreshold.data.filter(
        (element) => {
          if (
            dayjs(element.id.endDate).isSame(dayjs(item?.endDate)) &&
            dayjs(element.id.startDate).isSame(dayjs(item?.startDate))
          ) {
            return null;
          }

          return item;
        },
      );

    setThresholdAnomalyDetectionData({
      ...thresholdsAnomalyDetectionData,
      exclusionPeriodsThreshold: {
        ...thresholdsAnomalyDetectionData.exclusionPeriodsThreshold,
        data: [...newData],
      },
    });
  };

  return (
    <div
      style={{
        alignItems: 'center',
        display: 'flex',
        flexDirection: 'row',
        justifyContent: 'space-between',
      }}
    >
      <div style={{ fontSize: 13.5, fontWeight: 'bold' }}>From</div>
      <div style={{ fontSize: 13.5 }}>
        {dayjs(item?.start as Date).format('L LT S')}
      </div>
      <div style={{ fontSize: 14, fontWeight: 'bold' }}>To</div>
      <div style={{ fontSize: 13.5 }}>
        {dayjs(item?.end as Date).format('L LT S')}
      </div>
      <IconButton aria-label="delete" onClick={deletePeriod}>
        <DeleteIcon />
      </IconButton>
    </div>
  );
};

export default AnomalyDetectionItemsExclusionPeriods;
