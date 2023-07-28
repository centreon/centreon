import { createStore } from 'jotai';

import { Module } from '@centreon/ui';

interface Props {
  store: ReturnType<typeof createStore>;
}

const Input = ({ store }: Props): JSX.Element => {
  return (
    <Module maxSnackbars={1} seedName="widget-graph" store={store}>
      <div />
    </Module>
  );
};

export default Input;
