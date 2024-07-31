import { equals } from 'ramda';

import { Module } from '@centreon/ui';

import { CommonWidgetProps } from '../../models';

import { PanelOptions } from './models';
import Clock from './Clock';

import 'dayjs/locale/en';
import 'dayjs/locale/pt';
import 'dayjs/locale/fr';
import 'dayjs/locale/es';
import 'dayjs/locale/de';
import dayjs from 'dayjs';
import localizedFormat from 'dayjs/plugin/localizedFormat';
import utcPlugin from 'dayjs/plugin/utc';
import timezonePlugin from 'dayjs/plugin/timezone';

dayjs.extend(localizedFormat);
dayjs.extend(utcPlugin);
dayjs.extend(timezonePlugin);

interface Props extends CommonWidgetProps<PanelOptions> {
  panelOptions: PanelOptions;
}

const Widget = ({ store, queryClient, panelOptions }: Props): JSX.Element => (
  <Module queryClient={queryClient} seedName="clock" store={store}>
    {equals(panelOptions.displayType, 'clock') ? (
      <Clock {...panelOptions} />
    ) : (
      <p>Timer</p>
    )}
  </Module>
);

export default Widget;
