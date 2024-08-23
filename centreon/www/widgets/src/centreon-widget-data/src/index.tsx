import { createStore } from 'jotai';

import { FluidTypography, Module } from '@centreon/ui';

interface Props {
  children: JSX.Element;
  store: ReturnType<typeof createStore>;
}

const Data = ({ store, children }: Props): JSX.Element => {
  return (
    <Module maxSnackbars={1} seedName="text" store={store}>
      <div
        style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(2, minmax(60px, auto))'
        }}
      >
        <FluidTypography text="Hello world" />
        {children}
      </div>
    </Module>
  );
};

export default Data;
