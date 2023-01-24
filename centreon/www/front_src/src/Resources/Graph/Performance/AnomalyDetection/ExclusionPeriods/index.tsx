/* eslint-disable hooks/sort */
import { useEffect, useState } from 'react';

import dayjs from 'dayjs';
import { useAtom } from 'jotai';
import { useAtomValue } from 'jotai/utils';
import { equals, find, isEmpty, isNil, path, propEq } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import AddIcon from '@mui/icons-material/Add';
import {
  Button,
  Divider,
  List,
  ListItem,
  ListItemText,
  Typography
} from '@mui/material';

import { getData, useRequest } from '@centreon/ui';

import { detailsAtom } from '../../../../Details/detailsAtoms';
import {
  labelButtonExcludeAPeriod,
  labelConfirmationExclusionPeriod,
  labelExcludedPeriods,
  labelExclusionOfPeriods,
  labelSubTitleExclusionOfPeriods,
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
import AnomalyDetectionItemsExclusionPeriod from './AnomalyDetectionItemsExclusionPeriod';
import AnomalyDetectionTitleExclusionPeriod from './AnomalyDetectionTitleExclusionPeriod';

const useStyles = makeStyles()((theme) => ({
  body: {
    display: 'flex',
    justifyContent: 'center',
    marginTop: theme.spacing(5)
  },
  container: {
    backgroundColor: theme.palette.background.default,
    display: 'flex',
    padding: theme.spacing(2)
  },
  divider: {
    margin: theme.spacing(0, 2)
  },
  error: {
    textAlign: 'left'
  },

  excludedPeriods: {
    display: 'flex',
    flexDirection: 'column',
    width: '55%'
  },
  exclusionButton: {
    width: theme.spacing(22.5)
  },
  list: {
    backgroundColor: theme.palette.action.disabledBackground,
    maxHeight: theme.spacing(150 / 8),
    minHeight: theme.spacing(150 / 8),
    overflow: 'auto',
    padding: theme.spacing(1)
  },
  paper: {
    backgroundColor: theme.palette.background.default,
    padding: theme.spacing(2)
  },
  picker: {
    flexDirection: 'row',
    padding: 0
  },
  subContainer: {
    display: 'flex',
    flexDirection: 'column'
  },
  title: {
    color: theme.palette.text.disabled
  }
}));

const AnomalyDetectionExclusionPeriod = (): JSX.Element => {
  const { classes } = useStyles();

  const [open, setOpen] = useState(false);
  const [endDate, setEndDate] = useState<Date | null>(null);
  const [startDate, setStartDate] = useState<Date | null>(null);
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

  const exclude = (): void => {
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
    const filteredData = data.map((item) => {
      if (equals(item?.isConfirmed, false)) {
        return null;
      }

      return item;
    });
    console.log({ filteredData });

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
      <div className={classes.subContainer}>
        <Typography data-testid={labelExclusionOfPeriods} variant="h6">
          {labelExclusionOfPeriods}
        </Typography>
        <Typography
          data-testid={labelSubTitleExclusionOfPeriods}
          variant="caption"
        >
          {labelSubTitleExclusionOfPeriods}
        </Typography>
        <div className={classes.body}>
          <Button
            className={classes.exclusionButton}
            data-testid={labelButtonExcludeAPeriod}
            disabled={enabledExclusionButton}
            size="small"
            startIcon={<AddIcon />}
            variant="contained"
            onClick={exclude}
          >
            {labelButtonExcludeAPeriod}
          </Button>
        </div>
      </div>
      <Divider flexItem className={classes.divider} orientation="vertical" />
      <div className={classes.excludedPeriods}>
        <Typography
          className={classes.title}
          data-testid={labelExcludedPeriods}
          variant="h6"
        >
          {labelExcludedPeriods}
        </Typography>
        <List className={classes.list} data-testid="listExcludedPeriods">
          {data.map((item) => {
            const dateExist =
              !isNil(item?.id?.start) &&
              !isNil(item?.id?.end) &&
              item.isConfirmed;

            return (
              dateExist && (
                <ListItem
                  disablePadding
                  key={`${item?.id?.start}-${item?.id?.end}`}
                >
                  <ListItemText
                    primary={
                      <AnomalyDetectionItemsExclusionPeriod item={item?.id} />
                    }
                  />
                </ListItem>
              )
            );
          })}
        </List>
      </div>
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
