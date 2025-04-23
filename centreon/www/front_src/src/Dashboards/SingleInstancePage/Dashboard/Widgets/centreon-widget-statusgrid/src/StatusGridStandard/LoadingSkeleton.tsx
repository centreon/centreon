import { HeatMap, LoadingSkeleton } from '@centreon/ui';

const skeletons = Array(20)
  .fill(0)
  .map((_, idx) => ({
    backgroundColor: 'transparent',
    data: {},
    id: `${idx}`
  }));

const HeatMapSkeleton = (): JSX.Element => {
  return (
    <HeatMap tiles={skeletons}>
      {() => (
        <LoadingSkeleton height="100%" variant="rectangular" width="100%" />
      )}
    </HeatMap>
  );
};

export default HeatMapSkeleton;
