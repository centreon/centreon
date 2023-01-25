/* eslint-disable hooks/sort */
import { useEffect, useState } from 'react';

import dayjs from 'dayjs';
import { useAtom } from 'jotai';
import { useAtomValue } from 'jotai/utils';
import { equals, find, isNil, path, propEq } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { getData, useRequest } from '@centreon/ui';

import { detailsAtom } from '../../../../Details/detailsAtoms';
import {
  labelConfirmationExclusionPeriod,
  titleExcludeAPeriod
} from '../../../../translatedLabels';
import { GraphData, Line, TimeValue } from '../../models';
import PopoverCustomTimePeriodPickers from '../../TimePeriods/PopoverCustomTimePeriodPicker';
import {
  customTimePeriodAtom,
  graphQueryParametersDerivedAtom
} from '../../TimePeriods/timePeriodAtoms';
import { getLineData, getTimeSeries } from '../../timeSeries';
import { exclusionPeriodsThresholdAtom } from '../anomalyDetectionAtom';
import AnomalyDetectionModalConfirmation from '../AnomalyDetectionModalConfirmation';

import AnomalyDetectionCommentExclusionPeriod from './AnomalyDetectionCommentExclusionPeriod';
import AnomalyDetectionFooterExclusionPeriod from './AnomalyDetectionFooterExclusionPeriod';
import AnomalyDetectionManageExclusionPeriodInterface from './AnomalyDetectionManageExclusionPeriodInterface';
import AnomalyDetectionTitleExclusionPeriod from './AnomalyDetectionTitleExclusionPeriod';

const useStyles = makeStyles()((theme) => ({
  container: {
    backgroundColor: theme.palette.background.default,
    display: 'flex',
    padding: theme.spacing(2)
  },
  error: {
    textAlign: 'left'
  },
  exclusionButton: {
    width: theme.spacing(22.5)
  },
  paper: {
    backgroundColor: theme.palette.background.default,
    padding: theme.spacing(2)
  },
  picker: {
    flexDirection: 'row',
    padding: 0
  },
  title: {
    color: theme.palette.text.disabled
  }
}));

const AnomalyDetectionExclusionPeriod = (): JSX.Element => {
  const { classes } = useStyles();

  const [open, setOpen] = useState(false);
  const [endDate, setEndDate] = useState<Date | ''>('');
  const [startDate, setStartDate] = useState<Date | ''>('');
  const [timeSeries, setTimeSeries] = useState<Array<TimeValue> | null>(null);
  const [lineData, setLineData] = useState<Array<Line> | null>(null);
  const [isErrorDatePicker, setIsErrorDatePicker] = useState(false);
  const [enabledExclusionButton, setEnabledExclusionButton] = useState(false);
  const [isExclusionPeriodChecked, setIsExclusionPeriodChecked] =
    useState(false);
  const [disabledPickerEndInput, setDisabledPickerEndInput] = useState(false);
  const [disabledPickerStartInput, setDisabledPickerStartInput] =
    useState(false);
  const [isClosedStartPicker, setIsClosedStartPicker] =
    useState<boolean>(false);
  const [isClosedEndPicker, setIsClosedEndPicker] = useState<boolean>(false);
  const [pickerStartWithoutInitialValue, setPickerStartWithoutInitialValue] =
    useState(true);
  const [pickerEndWithoutInitialValue, setPickerEndWithoutInitialValue] =
    useState(true);

  const [isConfirmedExclusion, setIsConfirmedExclusion] = useState(false);

  const { sendRequest: sendGetGraphDataRequest } = useRequest<GraphData>({
    request: getData
  });

  const [exclusionPeriodsThreshold, setExclusionPeriodsThreshold] = useAtom(
    exclusionPeriodsThresholdAtom
  );
  const customTimePeriod = useAtomValue(customTimePeriodAtom);
  const getGraphQueryParameters = useAtomValue(graphQueryParametersDerivedAtom);
  const details = useAtomValue(detailsAtom);

  const [exclusionTimePeriods, setExclusionTimePeriods] =
    useState(customTimePeriod);

  const dateExisted = !!(startDate && endDate);

  const isInvalidDate = ({ start, end }): boolean =>
    dayjs(start).isSameOrAfter(dayjs(end), 'minute');

  const { data } = exclusionPeriodsThreshold;

  const endpoint = path(['links', 'endpoints', 'performance_graph'], details);

  const maxDateEndInputPicker = dayjs(exclusionTimePeriods?.end).add(1, 'day');
  const anchorPosition = {
    left: window.innerWidth / 2,
    top: window.innerHeight / 2
  };

  const excludeAPeriod = (): void => {
    setOpen(true);
    setExclusionTimePeriods(customTimePeriod);
    setEndDate(null);
    setStartDate(null);
  };

  const close = (): void => {
    setEndDate(null);
    setStartDate(null);
    setOpen(false);
  };

  const changeDate = ({ property, date }): void => {
    if (equals(property, 'end')) {
      setEndDate(date);

      return;
    }
    setStartDate(date);
  };

  const graphEndpoint = (): string | undefined => {
    const graphQueryParameters = getGraphQueryParameters({
      endDate,
      startDate
    });

    return `${endpoint}${graphQueryParameters}`;
  };

  const addCurrentData = (): void => {
    const newItem = {
      id: { end: endDate, start: startDate },
      isConfirmed: false,
      lines: lineData,
      timeSeries
    };

    setExclusionPeriodsThreshold({
      ...exclusionPeriodsThreshold,
      data: [...exclusionPeriodsThreshold.data, newItem]
    });
  };

  const deleteCurrentData = (): void => {
    const filteredData = data.filter(
      (item) => !equals(item.isConfirmed, false)
    );

    setExclusionPeriodsThreshold({
      data: filteredData
    });
  };

  const initializeData = (): void => {
    setStartDate(null);
    setEndDate(null);
    setPickerEndWithoutInitialValue(true);
    setPickerStartWithoutInitialValue(true);
    setIsExclusionPeriodChecked(false);
    setDisabledPickerEndInput(false);
    setDisabledPickerStartInput(false);
  };

  // call api confirmation
  const confirmExcluderPeriods = (): void => {
    const excludedData = data.map((item) => ({ ...item, isConfirmed: true }));

    setExclusionPeriodsThreshold({
      ...exclusionPeriodsThreshold,
      data: [...excludedData]
    });
    setOpen(false);
    initializeData();
    setIsConfirmedExclusion(false);
  };

  const cancelExclusionPeriod = (): void => {
    deleteCurrentData();
    setOpen(false);
    initializeData();
  };

  const onCloseStartPicker = (isClosed: boolean): void => {
    setIsClosedStartPicker(isClosed);
  };
  const onCloseEndPicker = (isClosed: boolean): void => {
    setIsClosedEndPicker(isClosed);
  };

  const getIsError = (value: boolean): void => {
    setIsErrorDatePicker(value);
  };

  const handleCheckedExclusionPeriod = ({ target }): void => {
    setIsExclusionPeriodChecked(target.checked);
    if (!target.checked) {
      setStartDate(null);
      setEndDate(null);
      setPickerStartWithoutInitialValue(true);
      setPickerEndWithoutInitialValue(true);
      setDisabledPickerEndInput(false);
      setDisabledPickerStartInput(false);

      return;
    }
    setPickerStartWithoutInitialValue(false);
    setPickerEndWithoutInitialValue(false);
    setDisabledPickerEndInput(true);
    setDisabledPickerStartInput(true);
    setStartDate(exclusionTimePeriods.start);
    setEndDate(exclusionTimePeriods.end);
  };

  useEffect(() => {
    if (!startDate || !endDate) {
      return;
    }
    if (!isClosedEndPicker || !isClosedStartPicker) {
      return;
    }
    console.log('call api');
    getGraphData(graphEndpoint());
  }, [startDate, endDate]);

  useEffect(() => {
    if (!isExclusionPeriodChecked) {
      deleteCurrentData();
    }
    if (!startDate || !endDate) {
      return;
    }
    getGraphData(graphEndpoint());
  }, [isExclusionPeriodChecked]);

  const getGraphData = (api: string | undefined): void => {
    sendGetGraphDataRequest({
      endpoint: api
    })
      .then((graphData) => {
        console.log({ graphData });
        setIsClosedEndPicker(false);
        setIsClosedStartPicker(false);
        setTimeSeries(getTimeSeries(graphData));
        const newLineData = getLineData(graphData);

        if (lineData) {
          setLineData(
            newLineData.map((line) => ({
              ...line,
              display:
                find(propEq('name', line.name), lineData)?.display ?? true
            }))
          );

          return;
        }

        setLineData(newLineData);
      })
      .catch(() => undefined);
  };

  useEffect(() => {
    if (isNil(lineData) || isNil(timeSeries)) {
      return;
    }
    addCurrentData();
  }, [timeSeries, lineData]);

  useEffect(() => {
    setEnabledExclusionButton(
      isInvalidDate({
        end: customTimePeriod?.end,
        start: customTimePeriod?.start
      })
    );
  }, [customTimePeriod]);

  return (
    <div className={classes.container}>
      <AnomalyDetectionManageExclusionPeriodInterface
        data={data}
        enabledExclusionButton={enabledExclusionButton}
        excludeAPeriod={excludeAPeriod}
      />
      <PopoverCustomTimePeriodPickers
        acceptDate={changeDate}
        anchorReference="anchorPosition"
        classNameError={classes.error}
        classNamePaper={classes.paper}
        classNamePicker={classes.picker}
        customTimePeriod={exclusionTimePeriods}
        disabledPickerEndInput={disabledPickerEndInput}
        disabledPickerStartInput={disabledPickerStartInput}
        getIsErrorDatePicker={getIsError}
        maxDatePickerEndInput={maxDateEndInputPicker}
        minDatePickerStartInput={exclusionTimePeriods?.start}
        open={open}
        pickerEndWithoutInitialValue={pickerStartWithoutInitialValue}
        pickerStartWithoutInitialValue={pickerEndWithoutInitialValue}
        reference={{ anchorPosition }}
        renderBody={
          <AnomalyDetectionCommentExclusionPeriod
            isExclusionPeriodChecked={isExclusionPeriodChecked}
            onChangeCheckedExclusionPeriod={handleCheckedExclusionPeriod}
          />
        }
        renderFooter={
          <AnomalyDetectionFooterExclusionPeriod
            cancelExclusionPeriod={cancelExclusionPeriod}
            confirmExcluderPeriods={(): void => setIsConfirmedExclusion(true)}
            dateExisted={dateExisted}
            isError={isErrorDatePicker}
          />
        }
        renderTitle={<AnomalyDetectionTitleExclusionPeriod />}
        setPickerEndWithoutInitialValue={setPickerStartWithoutInitialValue}
        setPickerStartWithoutInitialValue={setPickerEndWithoutInitialValue}
        onClose={close}
        onCloseEndPicker={onCloseEndPicker}
        onCloseStartPicker={onCloseStartPicker}
      />

      <AnomalyDetectionModalConfirmation
        dataTestid="modalConfirmationExclusionPeriod"
        message={labelConfirmationExclusionPeriod}
        open={isConfirmedExclusion}
        setOpen={setIsConfirmedExclusion}
        title={titleExcludeAPeriod}
        onCancel={(value): void => setIsConfirmedExclusion(value)}
        onConfirm={confirmExcluderPeriods}
      />
    </div>
  );
};

export default AnomalyDetectionExclusionPeriod;
