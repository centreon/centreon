import LoadingSkeleton from '../LoadingSkeleton';

const ListingLoadingSkeleton = (): JSX.Element => (
  <>
    {['skeleton1', 'skeleton2', 'skeleton3'].map((key) => (
      <LoadingSkeleton height={20} key={key} variant="text" width="100%" />
    ))}
  </>
);

export default ListingLoadingSkeleton;
