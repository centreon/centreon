/* eslint-disable react/prop-types */

import * as React from 'react';

import useMemoComponent from '../utils/useMemoComponent';

import Panel, { Props } from '.';

const MemoizedPanel = React.forwardRef<HTMLDivElement, Props>(
  (
    {
      memoProps = [],
      tabs,
      selectedTabId,
      labelClose,
      width,
      minWidth,
      headerBackgroundColor,
      ...props
    }: Props,
    ref,
  ): JSX.Element => {
    return useMemoComponent({
      Component: (
        <Panel
          tabs={tabs}
          selectedTabId={selectedTabId}
          labelClose={labelClose}
          width={width}
          minWidth={minWidth}
          headerBackgroundColor={headerBackgroundColor}
          ref={ref}
          {...props}
        />
      ),
      memoProps: [
        ...memoProps,
        selectedTabId,
        labelClose,
        width,
        minWidth,
        headerBackgroundColor,
      ],
    });
  },
);

export default MemoizedPanel;
