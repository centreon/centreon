import { ReactElement, ReactNode } from 'react';

type AreaIndicatorProps = {
  children?: ReactNode | Array<ReactNode>;
  depth?: number;
  height?: number | string;
  name: string;
  width?: number | string;
};

const AreaIndicator = ({
  children,
  name = 'area',
  width = '100%',
  height = '100%',
  depth = 0
}: AreaIndicatorProps): ReactElement => {
  return (
    <div
      className={'bg-secondary-main/25 relative min-h-8'}
      data-depth={depth}
      style={{ height, width }}
    >
      {/* biome-ignore lint/a11y: */}
      <label className="left-2 rounded-sm border border-[#9747FF7F] border-dashed text-[#9747FF] text-[0.75rem] font-medium left-2 absolute top-1.5 px-2 py-0.5">
        {name}
      </label>
      {children}
    </div>
  );
};

export { AreaIndicator };
