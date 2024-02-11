import { createStore } from 'jotai';

import { Module } from '@centreon/ui';

interface Props {
  store: ReturnType<typeof createStore>;
}

const Widget = ({ store, ...props }: Props): JSX.Element => (
  <Module maxSnackbars={1} seedName="widget-statuschart" store={store}>
    <div>status chart</div>
  </Module>
);

export default Widget;
