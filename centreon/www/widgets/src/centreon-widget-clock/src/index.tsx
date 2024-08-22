import 'dayjs/locale/en';
import 'dayjs/locale/pt';
import 'dayjs/locale/fr';
import 'dayjs/locale/es';
import 'dayjs/locale/de';

import dayjs from 'dayjs';
import duration from 'dayjs/plugin/duration';
import localizedFormat from 'dayjs/plugin/localizedFormat';
import timezonePlugin from 'dayjs/plugin/timezone';
import utcPlugin from 'dayjs/plugin/utc';
import { equals } from 'ramda';

import { Module } from '@centreon/ui';

import { CommonWidgetProps } from '../../models';

import Clock from './Clock';
import Timer from './Timer';
import { ForceDimension, PanelOptions } from './models';

dayjs.extend(localizedFormat);
dayjs.extend(utcPlugin);
dayjs.extend(timezonePlugin);
dayjs.extend(duration);

interface Props extends CommonWidgetProps<PanelOptions> {
  panelOptions: PanelOptions;
}

const Widget = ({
  store,
  queryClient,
  panelOptions,
  hasDescription,
  forceHeight,
  forceWidth
}: Props & ForceDimension): JSX.Element => (
  <Module queryClient={queryClient} seedName="clock" store={store}>
    {equals(panelOptions.displayType, 'clock') ? (
      <Clock
        {...panelOptions}
        forceHeight={forceHeight}
        forceWidth={forceWidth}
        hasDescription={hasDescription}
      />
    ) : (
      <Timer
        {...panelOptions}
        forceHeight={forceHeight}
        forceWidth={forceWidth}
        hasDescription={hasDescription}
      />
    )}
  </Module>
);

export default Widget;
