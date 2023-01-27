/* eslint-disable react/prop-types */

import { forwardRef } from 'react';

import useMemoComponent from '../utils/useMemoComponent';

import Panel, { Props } from '.';

interface MemoizedPanelProps extends Props {
  memoProps?: Array<unknown>;
}

const MemoizedPanel = forwardRef<HTMLDivElement, MemoizedPanelProps>(
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
    }: MemoizedPanelProps,
    ref
  ): JSX.Element => {
    return useMemoComponent({
      Component: (
        <Panel
          headerBackgroundColor={headerBackgroundColor}
          labelClose={labelClose}
          minWidth={minWidth}
          ref={ref}
          selectedTabId={selectedTabId}
          tabs={tabs}
          width={width}
          {...props}
        />
      ),
      memoProps: [
        ...memoProps,
        selectedTabId,
        labelClose,
        width,
        minWidth,
        headerBackgroundColor
      ]
    });
  }
);

export default MemoizedPanel;
